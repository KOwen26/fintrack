<?php

use App\Enums\Concerns\HasValueChecker;

enum TestEnum: string
{
    use HasValueChecker;

    case Word = 'word';
    case TwoWord = 'two_word';
    case SpaceWord = 'space word';
    case TitleWord = 'Title Word';
}

describe('EnumValueChecker Trait', function (): void {
    it('returns true for correct enum value', function (TestEnum $enum, string $method): void {
        expect($enum->{$method}())->toBeTrue();
    })->with([
        'camelCase of its own value' => [TestEnum::Word, 'isWord'],
        'snake_case of its own value' => [TestEnum::Word, 'is_word'],
        'camelCase across two words' => [TestEnum::TwoWord, 'isTwoWord'],
        'snake_case across two words' => [TestEnum::TwoWord, 'is_two_word'],
        'camelCase across a space' => [TestEnum::SpaceWord, 'isSpaceWord'],
        'snake_case across a space' => [TestEnum::SpaceWord, 'is_space_word'],
        'camelCase of a Title Case value' => [TestEnum::TitleWord, 'isTitleWord'],
        'snake_case of a Title Case value' => [TestEnum::TitleWord, 'is_title_word'],
    ]);

    it('returns false for incorrect enum value', function (TestEnum $enum, string $method): void {
        expect($enum->{$method}())->toBeFalse();
    })->with([
        'camelCase of a different value' => [TestEnum::Word, 'isTwoWord'],
        'snake_case of a different value' => [TestEnum::Word, 'is_two_word'],
        'camelCase of a single-word value' => [TestEnum::TwoWord, 'isWord'],
        'snake_case of a single-word value' => [TestEnum::TwoWord, 'is_word'],
        'camelCase across different casing' => [TestEnum::SpaceWord, 'isTitleWord'],
        'camelCase across a different separator' => [TestEnum::TitleWord, 'isSpaceWord'],
        'camelCase of another value' => [TestEnum::TitleWord, 'isWord'],
    ]);

    describe('invalid method calls', function (): void {
        it('throws BadMethodCallException for methods not starting with is or is_', function (): void {
            expect(fn () => TestEnum::Word->invalidMethod())
                ->toThrow(BadMethodCallException::class, 'Method invalidMethod does not exist.');
        });

        it('throws BadMethodCallException for non-matching enum values', function (string $method): void {
            $enum = TestEnum::Word;
            expect(fn () => $enum->{$method}())
                ->toThrow(
                    BadMethodCallException::class,
                    "Method {$method} must be in camelCase (e.g., isTwoWord) or snake_case (e.g., is_two_word) matching an enum value."
                );
        })->with(['isInvalidValue', 'is_invalid_value']);
    });
});
