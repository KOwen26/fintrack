<?php

namespace App\Enums;

enum AccountAccessType: string
{
    case Personal = 'personal';
    case Shareable = 'shareable';
}
