<?php

namespace App\Enums;

use App\Enums\Concerns\HasValueChecker;

enum TestEnum: string
{
    use HasValueChecker;

    case WORD = 'word';
    case TWO_WORD = 'two_word';
    case SPACE_WORD = 'space word';
    case TITLE_WORD = 'Title Word';
}
