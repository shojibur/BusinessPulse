<?php

namespace App\Enums;

enum TaskDraftStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Superseded = 'superseded';
}
