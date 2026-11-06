<?php

namespace App\Services\Import;

use Carbon\CarbonImmutable;

/**
 * Duplicate identity for staged rows: same account + same day + same
 * whole-rupiah amount + normalized description = same fingerprint.
 */
final class ImportFingerprint
{
    public static function make(int $accountId, ?CarbonImmutable $date, ?float $amount, ?string $description): string
    {
        $normalizedDescription = mb_strtolower((string) preg_replace('/\s+/u', ' ', trim((string) $description)));

        return sha1(implode('|', [
            $accountId,
            $date?->toDateString() ?? 'no-date',
            (string) (int) round((float) $amount),
            $normalizedDescription,
        ]));
    }
}
