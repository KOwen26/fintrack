<?php

namespace App\Enums\Concerns;

use BadMethodCallException;
use Illuminate\Support\Str;

trait HasValueChecker
{
    public function __call(string $method, array $arguments)
    {
        // Check if method starts with 'is' or 'is_' and extract the value
        if (! preg_match('/^is_?(.+)$/', $method, $matches)) {
            throw new BadMethodCallException("Method {$method} does not exist.");
        }

        $value = Str::snake($matches[1]);
        $case_values = $this->normalizeCaseValue();
        $this->validateMethodName($method, $value, $case_values);

        return Str::of($this->value)->lower()->snake()->value() === $value;
    }

    protected function normalizeCaseValue()
    {
        return array_unique(array_map(fn ($case) => Str::of($case->value)->lower()->snake()->value(), self::cases()));
    }

    protected function validateMethodName(string $method, string $value, array $case_values)
    {
        // Validate that the method name is in the list of valid camelCase or snake_case names
        if (! in_array($value, $case_values, true)) {
            throw new BadMethodCallException("Method {$method} must be in camelCase (e.g., isTwoWord) or snake_case (e.g., is_two_word) matching an enum value.");
        }
    }
}
