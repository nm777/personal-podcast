<?php

namespace App\Enums;

enum ApprovalStatusType: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
