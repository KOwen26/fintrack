import icons from '@data/decoration-icons.json';

export type DecorationIcon = (typeof icons)[number];

export type DecorationIconGroup = DecorationIcon['group'];

export const decorationIcons = icons as readonly DecorationIcon[];

export function getDecorationIcon(slug: string): DecorationIcon | undefined {
    return decorationIcons.find((icon) => icon.slug === slug);
}

export function getDecorationIconsByGroup(group: DecorationIconGroup): DecorationIcon[] {
    return decorationIcons.filter((icon) => icon.group === group);
}

export const decorationIconGroups: DecorationIconGroup[] = [
    ...new Set(decorationIcons.map((icon) => icon.group)),
] as DecorationIconGroup[];
