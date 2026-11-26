<?php

namespace App\Actions\Platform;

use App\Models\PlatformAdmin;
use App\Services\AI\AiSettings;
use App\Services\AI\ChatRequest;
use App\Services\AI\Exceptions\ProviderException;
use App\Services\AI\Exceptions\RecoverableProviderException;
use App\Services\AI\OpenAiClient;

/**
 * Sends one tiny structured request with the saved text settings and reports a plain result. It proves the key, the
 * address, the model name and structured-output support work together. Nothing from the provider's answer is stored or
 * shown, only a fixed message.
 */
final class TestAiConnection
{
    public function __construct(private readonly OpenAiClient $client, private readonly PlatformAudit $audit) {}

    /** @return array{ok: bool, message: string} */
    public function handle(PlatformAdmin $admin): array
    {
        $endpoint = app(AiSettings::class)->text();

        if (! $endpoint->configured()) {
            return ['ok' => false, 'message' => 'The text settings are incomplete: an address, an API key and a model are all needed.'];
        }

        try {
            $this->client->chat(new ChatRequest(
                messages: [
                    ['role' => 'system', 'content' => 'Reply with a JSON object only.'],
                    ['role' => 'user', 'content' => 'Return {"ok": true}.'],
                ],
                schema: ['type' => 'object', 'additionalProperties' => false, 'required' => ['ok'], 'properties' => ['ok' => ['type' => 'boolean']]],
                briefId: 0,
                schemaName: 'connection_test',
                timeoutSeconds: 30,
            ));
            $result = ['ok' => true, 'message' => 'The provider answered correctly. Briefs and task extraction will use these settings.'];
        } catch (RecoverableProviderException $e) {
            $result = ['ok' => false, 'message' => match ($e->errorCode()) {
                'rate_limited' => 'The provider is rate limiting this key. Try again in a minute.',
                'timeout' => 'The provider did not answer in time. Check the address and try again.',
                default => 'The provider had a temporary problem. Try again shortly.',
            }];
        } catch (ProviderException $e) {
            $result = ['ok' => false, 'message' => $e->errorCode() === 'invalid_response'
                ? 'The provider answered, but not with the structured JSON this application needs. Check that the model supports structured output.'
                : 'The provider rejected the request. Check the API key, the address and the model name.'];
        }

        $this->audit->record($admin, 'ai.connection_tested', null, ['ok' => $result['ok'], 'provider' => $endpoint->provider]);

        return $result;
    }
}
