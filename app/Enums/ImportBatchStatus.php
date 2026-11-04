<?php

namespace App\Enums;

enum ImportBatchStatus: string
{
    case Staging = 'staging';
    case Approved = 'approved';
    case Discarded = 'discarded';
}
