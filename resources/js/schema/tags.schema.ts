import StringHelper from '@utilities/string-helper';

export const MAX_TAGS = 10;
export const MAX_TAG_LENGTH = 32;

/**
 * Best-effort preview of the backend normalization — the server re-normalizes
 * on every write and is authoritative. Mirrors TagService::MAX_*.
 */
export function normalizeTag(raw: string): string {
    return StringHelper.toSnakeCase(raw).slice(0, MAX_TAG_LENGTH);
}

export function normalizeTags(raw: string[]): string[] {
    return Array.from(new Set(raw.map(normalizeTag).filter(Boolean))).slice(0, MAX_TAGS);
}
