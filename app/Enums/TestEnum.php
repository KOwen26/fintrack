<?php

namespace App\Enums;

use App\Enums\Concerns\HasValueChecker;

enum TestEnum: string
{
    use HasValueChecker;

    case Word = 'word';
    case TwoWord = 'two_word';
    case SpaceWord = 'space word';
    case TitleWord = 'Title Word';
}
