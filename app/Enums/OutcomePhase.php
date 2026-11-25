<?php

namespace App\Enums;

enum OutcomePhase: string
{
    case PreAction = 'pre_action';
    case DuringAction = 'during_action';
    case PostAction = 'post_action';

    /** Only a post-action period can verify a resolution. */
    public function usedForVerification(): bool
    {
        return $this === self::PostAction;
    }
}
