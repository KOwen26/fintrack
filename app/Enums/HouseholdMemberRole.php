<?php

namespace App\Enums;

enum HouseholdMemberRole: string
{
    case Owner = 'owner';
    case Member = 'member';
}
