import colors from '@data/decoration-colors.json';

export type DecorationColor = (typeof colors)[number];

export type DecorationColorGroup = DecorationColor['group'];

export const decorationColors = colors as readonly DecorationColor[];

/**
 * Retrieve all active decoration colors.
 *
 * @returns {DecorationColor[]} Array of active DecorationColor records.
 */
export function getActiveDecorationColors(): DecorationColor[] {
    return decorationColors.filter((color) => color.status === 'Active');
}

/**
 * Find a decoration color by its slug.
 *
 * @param {string} slug - The unique slug identifying the decoration color.
 * @returns {DecorationColor | undefined} The matching DecorationColor or undefined.
 */
export function getDecorationColor(slug: string): DecorationColor | undefined {
    if (slug == null) {
        return undefined;
    }

    return decorationColors.find((color) => color.slug === slug);
}

/**
 * Get decoration colors for a given group.
 *
 * @param {DecorationColorGroup} group - The group name to filter by.
 * @returns {DecorationColor[]} Array of DecorationColor records in the group.
 */
export function getDecorationColorsByGroup(group: DecorationColorGroup): DecorationColor[] {
    if (group == null) {
        return [];
    }

    return decorationColors.filter((color) => color.group === group);
}

/**
 * All unique decoration color groups.
 */
export const decorationColorGroups: DecorationColorGroup[] = [
    ...new Set(decorationColors.map((color) => color.group)),
] as DecorationColorGroup[];
