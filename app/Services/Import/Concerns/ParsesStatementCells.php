<?php

namespace App\Services\Import\Concerns;

use Carbon\CarbonImmutable;

/**
 * Generic cell-value parsing for statement imports, shared across
 * providers: id-ID amount normalization (the app is single-currency
 * IDR, so this is valid app-wide) and date/time parsing against
 * per-provider format lists. Value parsing only — row assembly and
 * column layout belong to each provider's parser, because providers
 * differ in column conventions (debit/kredit pairs, single signed
 * columns, direction markers, split date/time rows).
 */
trait ParsesStatementCells
{
    /**
     * Date formats this provider's export uses — override in the parser
     * when the layout differs. Defaults to the common Indonesian
     * statement formats.
     *
     * @return list<string>
     */
    protected function statementDateFormats(): array
    {
        return ['d/m/Y', 'd-m-Y'];
    }

    /**
     * Time formats this provider's export uses — override in the parser
     * when the layout differs. Defaults to the common statement formats.
     *
     * @return list<string>
     */
    protected function statementTimeFormats(): array
    {
        return ['H:i:s', 'H:i'];
    }

    public function parseDate(?string $value): ?CarbonImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        foreach ($this->statementDateFormats() as $format) {
            // rawCreateFromFormat never throws on out-of-range values like
            // "31/13/2026" — it returns null. If the installed Carbon lacks
            // it, fall back to createFromFormat inside a try/catch (\Throwable)
            // returning null; the month-overflow test pins this behavior
            // either way.
            $date = CarbonImmutable::rawCreateFromFormat($format, trim($value));

            if ($date !== null) {
                return $date->startOfDay();
            }
        }

        return null;
    }

    public function parseTime(?string $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        // Strip a trailing timezone word — "12:49:41 WIB" → "12:49:41".
        $value = (string) preg_replace('/\s+[A-Za-z]+([+-]\d{1,2})?$/u', '', trim($value));

        if ($value === '') {
            return null;
        }

        foreach ($this->statementTimeFormats() as $format) {
            $time = CarbonImmutable::rawCreateFromFormat($format, $value);

            if ($time !== null) {
                return $time;
            }
        }

        return null;
    }

    /** id-ID amounts: "Rp1.000.000,00", "350.000,-", "-25.000". */
    public function parseAmount(string $value): ?float
    {
        $value = str_ireplace(['rp', ' ', "\u{A0}"], '', trim($value));

        if ($value === '' || $value === '-') {
            return null;
        }

        $value = str_replace(',-', ',', $value);
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
