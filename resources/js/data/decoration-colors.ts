import colors from '@data/decoration-colors.json';

export type DecorationColor = (typeof colors)[number];

export type DecorationColorGroup = DecorationColor['group'];

export const decorationColors = colors as readonly DecorationColor[];

export function getDecorationColor(slug: string): DecorationColor | undefined {
    return decorationColors.find((color) => color.slug === slug);
}

export function getDecorationColorsByGroup(group: DecorationColorGroup): DecorationColor[] {
    return decorationColors.filter((color) => color.group === group);
}

export const decorationColorGroups: DecorationColorGroup[] = [
    ...new Set(decorationColors.map((color) => color.group)),
] as DecorationColorGroup[];
