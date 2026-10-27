import colors from '@data/decoration-colors.json';
import icons from '@data/decoration-icons.json';

/**
 * Single decoration handler for icon and color presets — the lookup tables
 * behind the `decorations` slugs persisted on accounts and categories. Both
 * record kinds share the slug/group/status contract, so the query helpers
 * are written once against that shape; the icon and color sections re-export
 * them under their original names so callers never care which table a slug
 * lives in.
 */

interface DecorationRecord {
    slug: string;
    group: string;
    status: string;
}

function activeOf<T extends DecorationRecord>(records: readonly T[]): T[] {
    return records.filter((record) => record.status === 'Active');
}

function randomFrom<T extends DecorationRecord>(records: readonly T[]): T {
    const active = activeOf(records);

    return active[Math.floor(Math.random() * active.length)];
}

function findBySlug<T extends DecorationRecord>(
    records: readonly T[],
    slug?: string | null
): T | undefined {
    if (slug == null) {
        return undefined;
    }

    return records.find((record) => record.slug === slug);
}

function inGroup<T extends DecorationRecord>(records: readonly T[], group: T['group']): T[] {
    if (group == null) {
        return [];
    }

    return records.filter((record) => record.group === group);
}

function groupsOf<T extends DecorationRecord>(records: readonly T[]): T['group'][] {
    return [...new Set(records.map((record) => record.group))];
}

/* ── Icons ─────────────────────────────────────────────────────────── */

export type DecorationIcon = (typeof icons)[number];

export type DecorationIconGroup = DecorationIcon['group'];

export const decorationIcons = icons as readonly DecorationIcon[];

/**
 * Retrieve all active decoration icons.
 *
 * @returns {DecorationIcon[]} Array of active DecorationIcon records.
 */
export function getActiveDecorationIcons(): DecorationIcon[] {
    return activeOf(decorationIcons);
}

/**
 * Get a random active decoration icon.
 *
 * @returns {DecorationIcon} A random active DecorationIcon.
 */
export function getRandomDecorationIcon(): DecorationIcon {
    return randomFrom(decorationIcons);
}

/**
 * Find a decoration icon by its slug.
 *
 * @param {string} slug - The unique slug identifying the decoration icon.
 * @returns {DecorationIcon | undefined} The matching DecorationIcon or undefined.
 */
export function getDecorationIcon(slug?: string | null): DecorationIcon | undefined {
    return findBySlug(decorationIcons, slug);
}

/**
 * Get decoration icons for a given group.
 *
 * @param {DecorationIconGroup} group - The group name to filter by.
 * @returns {DecorationIcon[]} Array of DecorationIcon records in the group.
 */
export function getDecorationIconsByGroup(group: DecorationIconGroup): DecorationIcon[] {
    return inGroup(decorationIcons, group);
}

/**
 * All unique decoration icon groups.
 */
export const decorationIconGroups: DecorationIconGroup[] = groupsOf(decorationIcons);

/* ── Colors ────────────────────────────────────────────────────────── */

export type DecorationColor = (typeof colors)[number];

export type DecorationColorGroup = DecorationColor['group'];

export const decorationColors = colors as readonly DecorationColor[];

/**
 * Retrieve all active decoration colors.
 *
 * @returns {DecorationColor[]} Array of active DecorationColor records.
 */
export function getActiveDecorationColors(): DecorationColor[] {
    return activeOf(decorationColors);
}

/**
 * Get a random active decoration color.
 *
 * @returns {DecorationColor} A random active DecorationColor.
 */
export function getRandomDecorationColor(): DecorationColor {
    return randomFrom(decorationColors);
}

/**
 * Find a decoration color by its slug.
 *
 * @param {string} slug - The unique slug identifying the decoration color.
 * @returns {DecorationColor | undefined} The matching DecorationColor or undefined.
 */
export function getDecorationColor(slug?: string | null): DecorationColor | undefined {
    return findBySlug(decorationColors, slug);
}

/**
 * Get decoration colors for a given group.
 *
 * @param {DecorationColorGroup} group - The group name to filter by.
 * @returns {DecorationColor[]} Array of DecorationColor records in the group.
 */
export function getDecorationColorsByGroup(group: DecorationColorGroup): DecorationColor[] {
    return inGroup(decorationColors, group);
}

/**
 * All unique decoration color groups.
 */
export const decorationColorGroups: DecorationColorGroup[] = groupsOf(decorationColors);
