<?php

namespace App\Enums;

enum NotEvaluableReason: string
{
    case FollowupNotAfterBaseline = 'followup_not_after_baseline';
    case FollowupPeriodPartial = 'followup_period_partial';
    case BranchesChanged = 'branches_changed';
    case BaselineInsufficientData = 'baseline_insufficient_data';
    case FollowupInsufficientData = 'followup_insufficient_data';
    case ZeroBaselineValue = 'zero_baseline_value';
}
