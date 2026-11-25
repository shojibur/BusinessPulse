<?php

namespace App\Enums;

enum ActivitySubjectType: string
{
    case Import = 'Import';
    case RuleEvaluation = 'RuleEvaluation';
    case BusinessRule = 'BusinessRule';
    case Issue = 'Issue';
    case Brief = 'Brief';
    case Meeting = 'Meeting';
    case TaskDraft = 'TaskDraft';
    case Task = 'Task';
    case Outcome = 'Outcome';
}
