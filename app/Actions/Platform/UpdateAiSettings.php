<?php

namespace App\Actions\Platform;

use App\Models\AiSetting;
use App\Models\PlatformAdmin;

/**
 * Saves the site-wide AI settings. A blank API key field keeps the stored key; "clear" removes it (the `.env` value then
 * applies again). The audit entry lists the names of the fields that changed and never a key or its length.
 */
final class UpdateAiSettings
{
    private const PLAIN = [
        'text_provider', 'text_base_url', 'text_model',
        'transcription_base_url', 'transcription_model',
        'speech_base_url', 'speech_model', 'speech_voice',
    ];

    private const KEYS = ['text_api_key', 'transcription_api_key', 'speech_api_key'];

    public function __construct(private readonly PlatformAudit $audit) {}

    /** @param  array<string, mixed>  $input  validated request data */
    public function handle(PlatformAdmin $admin, array $input): AiSetting
    {
        $row = AiSetting::query()->orderBy('id')->first() ?? new AiSetting;
        $changed = [];

        foreach (self::PLAIN as $field) {
            $value = isset($input[$field]) && trim((string) $input[$field]) !== '' ? trim((string) $input[$field]) : null;

            if ($row->{$field} !== $value) {
                $changed[] = $field;
            }

            $row->{$field} = $value;
        }

        foreach (self::KEYS as $field) {
            $new = isset($input[$field]) ? trim((string) $input[$field]) : '';

            if ($new !== '') {
                $row->{$field} = $new;
                $changed[] = $field;
            } elseif (! empty($input['clear_'.$field])) {
                $row->{$field} = null;
                $changed[] = $field;
            }
        }

        $row->updated_by = $admin->id;
        $row->save();

        $this->audit->record($admin, 'ai.settings_updated', null, ['fields' => $changed]);

        return $row;
    }
}
