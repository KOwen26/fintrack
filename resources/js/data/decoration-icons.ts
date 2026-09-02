import icons from '@data/decoration-icons.json';

export type DecorationIcon = (typeof icons)[number];

export type DecorationIconGroup = DecorationIcon['group'];

export const decorationIcons = icons as readonly DecorationIcon[];

/**
 * Retrieve all active decoration icons.
 *
 * @returns {DecorationIcon[]} Array of active DecorationIcon records.
 */
export function getActiveDecorationIcons(): DecorationIcon[] {
    return decorationIcons.filter((icon) => icon.status === 'Active');
}

/**
 * Get a random active decoration icon.
 *
 * @returns {DecorationIcon | undefined} A random active DecorationIcon, or undefined when none exist.
 */
export function getRandomDecorationIcon(): DecorationIcon | undefined {
    const activeIcons = getActiveDecorationIcons();

    if (!activeIcons.length) {
        return undefined;
    }

    return activeIcons[Math.floor(Math.random() * activeIcons.length)];
}

/**
 * Find a decoration icon by its slug.
 *
 * @param {string} slug - The unique slug identifying the decoration icon.
 * @returns {DecorationIcon | undefined} The matching DecorationIcon or undefined.
 */
export function getDecorationIcon(slug: string): DecorationIcon | undefined {
    if (slug == null) {
        return undefined;
    }

    return decorationIcons.find((icon) => icon.slug === slug);
}

/**
 * Get decoration icons for a given group.
 *
 * @param {DecorationIconGroup} group - The group name to filter by.
 * @returns {DecorationIcon[]} Array of DecorationIcon records in the group.
 */
export function getDecorationIconsByGroup(group: DecorationIconGroup): DecorationIcon[] {
    if (group == null) {
        return [];
    }

    return decorationIcons.filter((icon) => icon.group === group);
}

/**
 * All unique decoration icon groups.
 */
export const decorationIconGroups: DecorationIconGroup[] = [
    ...new Set(decorationIcons.map((icon) => icon.group)),
] as DecorationIconGroup[];
