<?php

namespace App\Services\AI;

use App\Services\AI\Exceptions\ProviderException;
use App\Services\AI\Exceptions\RecoverableProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The one thin HTTP client for the AI provider (S05 brief and speech; S06 transcription and extraction reuse it).
 * ONE attempt per call: no retry and no sleep here. Retrying belongs to the queue jobs only (R6), so one user
 * action can never multiply attempts. Model, voice, base URL and key come from config('ai'); nothing is hardcoded.
 * Failures become coded exceptions; keys, bodies and evidence never reach exceptions or logs.
 */
class OpenAiClient
{
    public static function textConfigured(): bool
    {
        return self::filled('ai.base_url') && self::filled('ai.api_key') && self::filled('ai.text_model');
    }

    public static function speechConfigured(): bool
    {
        return self::filled('ai.base_url') && self::filled('ai.api_key') && self::filled('ai.speech_model') && self::filled('ai.speech_voice');
    }

    public function chat(ChatRequest $request): ChatResponse
    {
        $response = $this->send('/chat/completions', [
            'model' => config('ai.text_model'),
            'messages' => $request->messages,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => ['name' => $request->schemaName, 'strict' => true, 'schema' => $request->schema],
            ],
        ], (int) config('ai.timeouts.text', 30), 'chat', $request->briefId);

        return ChatResponse::fromJson($response->json());
    }

    public function speech(SpeechRequest $request): SpeechResponse
    {
        $response = $this->send('/audio/speech', [
            'model' => config('ai.speech_model'),
            'voice' => config('ai.speech_voice'),
            'input' => $request->text,
            'response_format' => 'mp3',
        ], (int) config('ai.timeouts.speech', 60), 'speech', $request->briefId);

        return SpeechResponse::fromBinary($response->body());
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function send(string $path, array $body, int $timeoutSeconds, string $requestType, int $briefId): Response
    {
        $started = microtime(true);

        try {
            $response = Http::baseUrl((string) config('ai.base_url'))
                ->withToken((string) config('ai.api_key'))
                ->acceptJson()
                ->timeout($timeoutSeconds)
                ->post($path, $body);
        } catch (ConnectionException) {
            $this->log($requestType, $briefId, null, $started);

            throw new RecoverableProviderException('timeout');
        }

        $status = $response->status();
        $this->log($requestType, $briefId, $status, $started);

        if ($status === 429) {
            throw new RecoverableProviderException('rate_limited');
        }

        if ($status >= 500) {
            throw new RecoverableProviderException('server_error');
        }

        if (! $response->successful()) {
            throw new ProviderException('client_error');
        }

        return $response;
    }

    /** Request type, brief id, status and duration only. */
    private function log(string $requestType, int $briefId, ?int $status, float $started): void
    {
        Log::info('ai_request', [
            'type' => $requestType,
            'brief_id' => $briefId,
            'status' => $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);
    }

    private static function filled(string $key): bool
    {
        $value = config($key);

        return is_string($value) && trim($value) !== '';
    }
}
