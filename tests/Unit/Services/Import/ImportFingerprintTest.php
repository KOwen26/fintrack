<?php

use App\Services\Import\ImportFingerprint;
use Carbon\CarbonImmutable;

it('is stable across case and whitespace differences in the description', function (): void {
    $a = ImportFingerprint::make(1, CarbonImmutable::parse('2026-10-31'), 2500000.0, 'TRSF  E-Banking   cr BCA');
    $b = ImportFingerprint::make(1, CarbonImmutable::parse('2026-10-31 08:30:00'), 2500000.0, 'trsf e-banking cr bca');

    expect($a)->toBe($b);
});

it('differs across account, date, or amount', function (): void {
    $base = ImportFingerprint::make(1, CarbonImmutable::parse('2026-10-31'), 2500000.0, 'same');

    expect($base)
        ->not->toBe(ImportFingerprint::make(2, CarbonImmutable::parse('2026-10-31'), 2500000.0, 'same'))
        ->not->toBe(ImportFingerprint::make(1, CarbonImmutable::parse('2026-10-30'), 2500000.0, 'same'))
        ->not->toBe(ImportFingerprint::make(1, CarbonImmutable::parse('2026-10-31'), 2600000.0, 'same'));
});

it('handles null parts deterministically', function (): void {
    expect(ImportFingerprint::make(1, null, null, null))
        ->toBe(ImportFingerprint::make(1, null, null, null))
        ->not->toBe(ImportFingerprint::make(1, null, null, 'described'));
});
