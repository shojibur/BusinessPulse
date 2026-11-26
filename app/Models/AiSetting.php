<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The single row of site-wide AI settings (S11). Keys are encrypted at rest and hidden from serialization.
 *
 * @property string|null $text_provider
 * @property string|null $text_base_url
 * @property string|null $text_api_key
 * @property string|null $text_model
 * @property string|null $transcription_base_url
 * @property string|null $transcription_api_key
 * @property string|null $transcription_model
 * @property string|null $speech_base_url
 * @property string|null $speech_api_key
 * @property string|null $speech_model
 * @property string|null $speech_voice
 */
class AiSetting extends Model
{
    protected $guarded = [];

    protected $hidden = ['text_api_key', 'transcription_api_key', 'speech_api_key'];

    protected function casts(): array
    {
        return [
            'text_api_key' => 'encrypted',
            'transcription_api_key' => 'encrypted',
            'speech_api_key' => 'encrypted',
        ];
    }
}
