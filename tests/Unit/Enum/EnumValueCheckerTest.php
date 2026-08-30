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
    // Test true conditions
    it('returns true for correct enum value', function (TestEnum $enum, string $method): void {
        expect($enum->{$method}())->toBeTrue();
    })->with([
        [TestEnum::Word, 'isWord'],
        [TestEnum::Word, 'is_word'],
        [TestEnum::TwoWord, 'isTwoWord'],
        [TestEnum::TwoWord, 'is_two_word'],
        [TestEnum::SpaceWord, 'isSpaceWord'],
        [TestEnum::SpaceWord, 'is_space_word'],
        [TestEnum::TitleWord, 'isTitleWord'],
        [TestEnum::TitleWord, 'is_title_word'],
    ]);

    // Test false conditions
    it('returns false for incorrect enum value', function (TestEnum $enum, string $method): void {
        expect($enum->{$method}())->toBeFalse();
    })->with([
        [TestEnum::Word, 'isTwoWord'],
        [TestEnum::Word, 'is_two_word'],
        [TestEnum::TwoWord, 'isWord'],
        [TestEnum::TwoWord, 'is_word'],
        [TestEnum::SpaceWord, 'isTitleWord'],
        [TestEnum::TitleWord, 'isSpaceWord'],
        [TestEnum::TitleWord, 'isWord'],
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
