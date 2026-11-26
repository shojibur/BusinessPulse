<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    |
    | OpenAI-compatible HTTP API. Every value comes from the environment and is
    | null when unset; no model name is hardcoded. S01 makes no provider call.
    | Retries are defined only in the queue jobs of later specifications.
    |
    */

    'base_url' => env('AI_BASE_URL'),

    'api_key' => env('OPENAI_API_KEY'),

    'text_model' => env('AI_TEXT_MODEL'),

    'transcription_model' => env('AI_TRANSCRIPTION_MODEL'),

    'speech_model' => env('AI_SPEECH_MODEL'),

    'speech_voice' => env('AI_SPEECH_VOICE'),

    // Fallbacks used when the platform admin has not set a value in the panel (S11). The text provider is `openai`
    // (any OpenAI-compatible API) or `anthropic` (Claude). Transcription and speech are always OpenAI-compatible.
    'text_provider' => env('AI_TEXT_PROVIDER', 'openai'),

    'anthropic_api_key' => env('ANTHROPIC_API_KEY'),

    // The queue the four AI jobs run on. One `default` queue keeps local setup and deployment simple (one worker);
    // set AI_QUEUE=ai and run a second worker (`--queue=ai`) only if AI work must not delay imports.
    'queue' => env('AI_QUEUE', 'default'),

    /*
    | HTTP timeouts in seconds, one attempt per call (docs/stage-1-refinements.md R6).
    */

    'timeouts' => [
        'text' => 30,
        'speech' => 60,
        'transcription' => 120,
        'extraction' => 60,
    ],

];
