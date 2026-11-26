<?php

namespace App\Services\AI;

use App\Services\AI\Exceptions\ProviderException;
use App\Services\AI\Exceptions\RecoverableProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Text answers (brief wording and task extraction) from Claude through the Messages API, with the same contract as the
 * OpenAI-compatible path: ONE attempt per call, coded exceptions, the structured answer returned as a ChatResponse.
 * Claude has no transcription or speech endpoints, so those stay on the OpenAI-compatible client.
 *
 * Raw HTTP through Laravel's client on purpose: the project has one thin HTTP layer for providers and its tests fake
 * `Http`. Keys, bodies and evidence never reach exceptions or logs.
 */
class AnthropicTextClient
{
    public const API_VERSION = '2026-10-21';

    /** A ceiling for one structured answer; the schemas in this application produce far less. */
    private const MAX_TOKENS = 8000;

    /** JSON Schema keywords the structured-output feature rejects; the server validates these itself after parsing. */
    private const UNSUPPORTED = ['minLength', 'maxLength', 'minimum', 'maximum', 'multipleOf', 'minItems', 'maxItems', 'pattern', 'uniqueItems'];

    public function chat(ChatRequest $request, AiEndpoint $endpoint): ChatResponse
    {
        $system = [];
        $messages = [];

        foreach ($request->messages as $message) {
            if ($message['role'] === 'system') {
                $system[] = $message['content'];
            } else {
                $messages[] = ['role' => $message['role'], 'content' => $message['content']];
            }
        }

        $body = [
            'model' => $endpoint->model,
            'max_tokens' => self::MAX_TOKENS,
            'messages' => $messages,
            'output_config' => ['format' => ['type' => 'json_schema', 'schema' => self::cleanSchema($request->schema)]],
        ];

        if ($system !== []) {
            $body['system'] = implode("\n\n", $system);
        }

        $started = microtime(true);

        try {
            $response = Http::baseUrl(rtrim((string) $endpoint->baseUrl, '/'))
                ->withHeaders(['x-api-key' => (string) $endpoint->apiKey, 'anthropic-version' => self::API_VERSION])
                ->acceptJson()
                ->timeout($request->timeoutSeconds ?? (int) config('ai.timeouts.text', 30))
                ->post('/v1/messages', $body);
        } catch (ConnectionException) {
            $this->log($request->briefId, null, $started);

            throw new RecoverableProviderException('timeout');
        }

        $this->log($request->briefId, $response->status(), $started);
        ProviderStatus::assertSuccessful($response->status(), $response->successful());

        $json = $response->json();

        // A refusal or a cut-off answer is never usable structured data.
        if (! is_array($json) || in_array($json['stop_reason'] ?? null, ['refusal', 'max_tokens'], true)) {
            throw new ProviderException('invalid_response');
        }

        $text = null;

        foreach ($json['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                $text = $block['text'];

                break;
            }
        }

        // Same shape as the OpenAI-compatible answer, so one parser (and one lenient mode) serves both providers.
        return ChatResponse::fromJson(['choices' => [['message' => ['content' => $text]]]], $request->lenientContent);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public static function cleanSchema(array $schema): array
    {
        foreach (self::UNSUPPORTED as $keyword) {
            unset($schema[$keyword]);
        }

        foreach ($schema as $key => $value) {
            if (is_array($value)) {
                $schema[$key] = self::cleanSchema($value);
            }
        }

        return $schema;
    }

    private function log(int $briefId, ?int $status, float $started): void
    {
        Log::info('ai_request', [
            'type' => 'chat',
            'provider' => 'anthropic',
            'brief_id' => $briefId,
            'status' => $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);
    }
}
