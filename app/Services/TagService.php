<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class TagService
{
    public const int MAX_TAGS = 10;

    public const int MAX_TAG_LENGTH = 32;

    /** Sanity ceiling for un-normalized client input — longer raw values 422 upstream. */
    public const int MAX_RAW_LENGTH = 64;

    /**
     * Lowercase, collapse non letter/number runs to one underscore, trim,
     * truncate, drop empties, dedupe preserving first-seen order, cap.
     *
     * @param  list<string>  $raw
     *
     * @return list<string>
     */
    public static function normalize(array $raw): array
    {
        $slugs = [];

        foreach ($raw as $value) {
            $slug = mb_substr(
                trim((string) preg_replace('/[^\p{L}\p{N}]+/u', '_', mb_strtolower((string) $value)), '_'),
                0,
                self::MAX_TAG_LENGTH,
            );

            if ($slug !== '' && ! in_array($slug, $slugs, true)) {
                $slugs[] = $slug;
            }

            if (count($slugs) === self::MAX_TAGS) {
                break;
            }
        }

        return $slugs;
    }

    /**
     * @param  list<string>  $raw
     *
     * @return list<string>|null
     */
    public static function normalizeToNullable(array $raw): ?array
    {
        $slugs = self::normalize($raw);

        return $slugs === [] ? null : $slugs;
    }

    /**
     * Distinct tags across the user’s live transactions, sorted. The JSON
     * arrays are exploded and de-duplicated in the database via JSON_TABLE —
     * raw SQL bypasses SoftDeletes, so the explicit deleted_at IS NULL is
     * load-bearing. VARCHAR(255) is unreachable for a normalized tag (cap
     * 32); a truncation there is a tripwire that upstream normalization
     * broke.
     *
     * @return list<string>
     */
    public static function getUniqueTags(User $user): array
    {
        $rows = DB::select(
            <<<'SQL'
            SELECT DISTINCT jt.tag
            FROM transactions t,
                 JSON_TABLE(t.tags, '$[*]' COLUMNS (tag VARCHAR (255) PATH '$')) jt
            WHERE t.created_by = ?
              AND t.deleted_at IS NULL
              AND t.tags IS NOT NULL
            ORDER BY jt.tag
            SQL,
            [$user->id],
        );

        return array_map(strval(...), array_column($rows, 'tag'));
    }
}
