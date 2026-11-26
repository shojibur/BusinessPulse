<?php

namespace App\Services\AI;

use App\Services\AI\Exceptions\ProviderException;
use App\Services\AI\Exceptions\RecoverableProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
        return app(AiSettings::class)->text()->configured();
    }

    public static function speechConfigured(): bool
    {
        return app(AiSettings::class)->speech()->configured();
    }

    /** Text answers: OpenAI-compatible, or Claude when the platform settings choose the `anthropic` provider. */
    public function chat(ChatRequest $request): ChatResponse
    {
        $endpoint = app(AiSettings::class)->text();

        if ($endpoint->provider === AiEndpoint::PROVIDER_ANTHROPIC) {
            return app(AnthropicTextClient::class)->chat($request, $endpoint);
        }

        $response = $this->send($endpoint, '/chat/completions', [
            'model' => $endpoint->model,
            'messages' => $request->messages,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => ['name' => $request->schemaName, 'strict' => true, 'schema' => $request->schema],
            ],
        ], $request->timeoutSeconds ?? (int) config('ai.timeouts.text', 30), 'chat', $request->briefId);

        return ChatResponse::fromJson($response->json(), $request->lenientContent);
    }

    public function speech(SpeechRequest $request): SpeechResponse
    {
        $endpoint = app(AiSettings::class)->speech();

        $response = $this->send($endpoint, '/audio/speech', [
            'model' => $endpoint->model,
            'voice' => $endpoint->voice,
            'input' => $request->text,
            'response_format' => 'mp3',
        ], (int) config('ai.timeouts.speech', 60), 'speech', $request->briefId);

        return SpeechResponse::fromBinary($response->body());
    }

    /**
     * One multipart attempt, same status mapping as chat(). The file stream is opened inside the call, so every queue
     * attempt sends a fresh stream. Only the model, the response format and the file are sent (no language, no prompt).
     */
    public function transcribe(TranscriptionRequest $request): TranscriptionResponse
    {
        $stream = Storage::disk($request->disk)->readStream($request->path);

        if (! is_resource($stream)) {
            throw new ProviderException('invalid_response');
        }

        $endpoint = app(AiSettings::class)->transcription();
        $started = microtime(true);

        try {
            $response = Http::baseUrl((string) $endpoint->baseUrl)
                ->withToken((string) $endpoint->apiKey)
                ->acceptJson()
                ->timeout($request->timeoutSeconds)
                ->asMultipart()
                ->attach('file', $stream, $request->uploadName, ['Content-Type' => $request->mime])
                ->post('/audio/transcriptions', [
                    ['name' => 'model', 'contents' => $request->model],
                    ['name' => 'response_format', 'contents' => 'json'],
                ]);
        } catch (ConnectionException) {
            $this->log('transcription', $request->meetingId, null, $started);

            throw new RecoverableProviderException('timeout');
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $this->log('transcription', $request->meetingId, $response->status(), $started);
        ProviderStatus::assertSuccessful($response->status(), $response->successful());

        return TranscriptionResponse::fromJson($response->json());
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function send(AiEndpoint $endpoint, string $path, array $body, int $timeoutSeconds, string $requestType, int $briefId): Response
    {
        $started = microtime(true);

        try {
            $response = Http::baseUrl((string) $endpoint->baseUrl)
                ->withToken((string) $endpoint->apiKey)
                ->acceptJson()
                ->timeout($timeoutSeconds)
                ->post($path, $body);
        } catch (ConnectionException) {
            $this->log($requestType, $briefId, null, $started);

            throw new RecoverableProviderException('timeout');
        }

        $status = $response->status();
        $this->log($requestType, $briefId, $status, $started);
        ProviderStatus::assertSuccessful($status, $response->successful());

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
}
