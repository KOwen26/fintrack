<?php

use App\Objects\Tags;

it('wraps normalized slugs', function (): void {
    expect(Tags::fromArray(['Bills & Rent!'])?->toArray())->toBe(['bills_rent']);
});

it('returns null for null and all-dropped input', function (?array $raw): void {
    expect(Tags::fromArray($raw))->toBeNull();
})->with([
    'null input' => [null],
    'empty list' => [[]],
    'symbol-only' => [['!!!']],
]);
