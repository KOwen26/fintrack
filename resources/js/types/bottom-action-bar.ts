import type { Snippet } from 'svelte';

/**
 * Page-declared bottom action bar, rendered by the mobile shell in place of
 * the dock. The contract is semantic, not visual: it declares that the page
 * owns the bottom action zone, while the shell decides how to present it.
 */
export type BottomActionBarContext = BottomActionBarCustomContext;

/**
 * Fully page-owned markup for the bottom action zone, e.g. a form's pinned
 * submit button. The snippet may only fill the bar's single-row zone and
 * must keep its own safe-area clearance.
 */
export interface BottomActionBarCustomContext {
    type: 'custom';
    render: Snippet;
}
