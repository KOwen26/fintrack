import type { Snippet } from 'svelte';

/**
 * Page-declared header context, rendered by whichever header serves the
 * current viewport (mobile context bar or desktop dashboard header).
 *
 * The contract is semantic, not visual: it describes what the page offers,
 * while each header decides how to present it for its viewport.
 */
export type HeaderContext = HeaderCustomContext;

/**
 * MVP escape hatch: fully page-owned markup. The snippet must adapt to both
 * viewports with responsive utility classes and may only fill the header's
 * fixed-height context zone.
 *
 * When two pages hand-build the same control inside a custom context, promote
 * that shape to a typed variant on `HeaderContext` instead.
 */
export interface HeaderCustomContext {
    type: 'custom';
    render: Snippet;
}
