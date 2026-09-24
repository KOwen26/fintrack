---
name: Fintrack
description: Light-first drenched bento money world — color owns regions, the amount owns the page.
---

# Design

<!-- SEED: established with the user before implementation; re-run $impeccable document once there's code to capture the actual tokens and components. -->

## Overview

**Bento Tegas — Senja** (user-locked 2026-09-24): a light-first, mobile-first bento world where saturated color owns whole regions instead of decorating neutral cards. The balance is a deep-indigo region under cream tabular numerals; income lands as sunset gold; transfers cool to slate blue; the commit action is ink. The signature is the amount hero — the largest element on any capture surface, with a gold caret. Surfaces are quiet white tiles on a warm light ground; energy comes from the drenched regions, never from shadows or ornament. Direction contract and first-viewport composition live in the surface brief, not here.

## Colors

Roles, semantics, and the values the locked exploration established. The app's token names stay DaisyUI-shaped (`primary`, `secondary`, `accent`, `base-*`, `success`, `info`, `warning`, `error`).

- **Ground**: warm light paper `#F4F3EF` (base surfaces); quieter tiles in near-white; deeper steps `#ECEAE3` / `#E2E0D8`.
- **Primary — deep indigo** `#2F3E9E` (deep step `#24317C`): owns balance and positive money regions; the amount hero; on-color text is cream `#F3EFE4`.
- **Secondary — sunset gold** `#E0A33C` (deep step `#B37F27`): income regions, the amount caret, and highlights earned by money coming in; dark ink text on gold, never cream.
- **Accent — slate blue** `#3D5FB0` (deep step `#324E8E`): transfers and informational regions; sparse everywhere else.
- **Neutral — ink** `#1D2030`: the commit button and primary text; cream `#F3EFE4` on ink.
- **Error — warm red** `#A83C22` family: expenses and destructive states only.

**The One-Hue-Per-Meaning Rule.** Indigo means balance/positive, gold means income, slate means transfer/info — never swapped, never scattered as accents over neutral ground. Color commits at region scale or stays out.

## Typography

A workhorse geometric grotesk with genuinely heavyweight tabular numerals. Display is numeral-first: the amount is always the largest type on any capture surface. UI copy is quiet mid-weight; labels are small, tracked, uppercase. Final faces `[to be resolved during implementation]` — mockups ran on system approximations, which are placeholders, not the voice.

## Layout

Mobile-first bento: a 4-column grid at 390px with 8–10px gutters; tiles span 2 or 4 columns. Reading order per capture surface: glance strip (balance / in / out) → amount hero (full width) → type pills on the hero → account rail → category mini-bento → keypad → commit at thumb height → today's feed. Density is welcome inside tiles; generous separation between regions. More space above a region than below it.

## Elevation & Depth

**The No-Stacked-Elevation Rule.** A tile declares elevation exactly once — a border or a drenched fill, never both, never stacked shadows. Drenched regions read as material, not floating cards; quiet tiles sit on the ground with a hairline edge.

## Shapes

Confident rectangles: 16px tile radius, 12px for keys and small controls, pills reserved for the type toggle and small chips only. One level of containment — no cards nested in cards.

## Do's and Don'ts

- Do keep the amount the hero of every capture surface, in tabular numerals.
- Do theme the browser surfaces — caret, selection, focus rings — from the palette.
- Don't reach for gradient text, glass decoration, emoji icons, or icon-plus-heading card scaffolds.
- Don't revive the refused worlds: ledger/thermal-receipt aesthetics, dark-emissive terminals, or bookkeeping nostalgia.
- Don't build dark-mode-first compositions; light is the scene. A dark mapping is deferred until the light world ships.
- Don't treat the mockups' system fonts or their single-flow scope as final; implementation resolves both.
