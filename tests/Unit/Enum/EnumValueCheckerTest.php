<?php

use App\Enums\TestEnum;

describe('EnumValueChecker Trait', function () {
    // Test true conditions
    it('returns true for correct enum value', function (TestEnum $enum, string $method) {
        expect($enum->{$method}())->toBeTrue();
    })->with([
        [TestEnum::WORD, 'isWord'],
        [TestEnum::WORD, 'is_word'],
        [TestEnum::TWO_WORD, 'isTwoWord'],
        [TestEnum::TWO_WORD, 'is_two_word'],
        [TestEnum::SPACE_WORD, 'isSpaceWord'],
        [TestEnum::SPACE_WORD, 'is_space_word'],
        [TestEnum::TITLE_WORD, 'isTitleWord'],
        [TestEnum::TITLE_WORD, 'is_title_word'],
    ]);

    // Test false conditions
    it('returns false for incorrect enum value', function (TestEnum $enum, string $method) {
        expect($enum->{$method}())->toBeFalse();
    })->with([
        [TestEnum::WORD, 'isTwoWord'],
        [TestEnum::WORD, 'is_two_word'],
        [TestEnum::TWO_WORD, 'isWord'],
        [TestEnum::TWO_WORD, 'is_word'],
        [TestEnum::SPACE_WORD, 'isTitleWord'],
        [TestEnum::TITLE_WORD, 'isSpaceWord'],
        [TestEnum::TITLE_WORD, 'isWord'],
    ]);

    describe('invalid method calls', function () {
        it('throws BadMethodCallException for methods not starting with is or is_', function () {
            expect(fn () => TestEnum::WORD->invalidMethod())
                ->toThrow(BadMethodCallException::class, 'Method invalidMethod does not exist.');
        });

        it('throws BadMethodCallException for non-matching enum values', function (string $method) {
            $enum = TestEnum::WORD;
            expect(fn () => $enum->{$method}())
                ->toThrow(
                    BadMethodCallException::class,
                    "Method {$method} must be in camelCase (e.g., isTwoWord) or snake_case (e.g., is_two_word) matching an enum value."
                );
        })->with(['isInvalidValue', 'is_invalid_value']);
    });
});
