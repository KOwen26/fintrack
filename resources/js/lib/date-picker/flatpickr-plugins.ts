import type { FlatpickrInstance, FlatpickrPlugin } from '@lib/date-picker/flatpickr-action.svelte';

const svgWrapper = (path: string) =>
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="${path}"/></svg>`;

/**
 * A flatpickr plugin rendering the 5×5 paged year grid for the `year` and
 * `year-range` picker types — raw flatpickr renders a day calendar, which this
 * strips and replaces.
 *
 * The whole flatpickr header is hidden via CSS in these modes; the action
 * wrapper registers this plugin in place of the year dropdown when
 * isYearPicker is set — the grid and its ±25-year pager are the entire popup.
 *
 * Range mode (mode: 'range') mirrors flatpickr's native range semantics: the
 * first click anchors a pending start, the second completes (and sorts) the
 * pair and closes; closing with only a start discards it. Ranges may span
 * pages — the pending start survives paging.
 */
export function yearSelectPlugin(): FlatpickrPlugin {
    const PAGE_SIZE = 25; // 5 × 5 grid

    return (fp: FlatpickrInstance) => {
        const isRange = () => fp.config.mode === 'range';

        // The grid's page anchor — deliberately decoupled from fp.currentYear:
        // the engine's setDate() recenters currentYear on the selection
        // (jumpToDate), which shifted the whole grid so the picked year jumped
        // to the center. Only the pager moves this anchor. Initialized lazily
        // on first build — plugins are constructed during parseConfig, before
        // currentYear exists.
        let pageAnchor: number | undefined;

        let hoverHighlights: HTMLElement[] = [];

        const clearHover = () => {
            hoverHighlights.forEach((cell) => cell.classList.remove('inRange'));

            hoverHighlights = [];
        };

        const build = () => {
            const rContainer = fp.rContainer;

            if (!rContainer) {
                return;
            }

            if (pageAnchor === undefined) {
                pageAnchor = fp.currentYear;
            }

            const minYear = fp.config.minDate ? fp.config.minDate.getFullYear() : -Infinity;
            const maxYear = fp.config.maxDate ? fp.config.maxDate.getFullYear() : Infinity;
            const pageStart = pageAnchor - Math.floor(PAGE_SIZE / 2);
            const pageEnd = pageStart + PAGE_SIZE - 1;
            const realYear = new Date().getFullYear();
            const selectedYears = fp.selectedDates.map((date) => date.getFullYear());
            const [startYear, endYear] =
                selectedYears.length === 2
                    ? [Math.min(...selectedYears), Math.max(...selectedYears)]
                    : [selectedYears[0], undefined];

            rContainer.innerHTML = '';
            hoverHighlights = [];

            const container = document.createElement('div');

            container.className = 'flatpickr-monthSelect-months fp-year-grid';
            container.tabIndex = -1;

            // Pager row — labelled with the visible range, buttons disabled once
            // the neighbouring page would fall entirely outside min/max.
            const pager = document.createElement('div');

            pager.className = 'fp-year-grid-pager';

            const prev = document.createElement('button');

            prev.type = 'button';
            prev.setAttribute('aria-label', 'Previous years');
            prev.innerHTML = svgWrapper('m15 18-6-6 6-6');
            prev.disabled = pageStart - 1 < minYear || pageStart - PAGE_SIZE > maxYear;
            prev.addEventListener('click', () => {
                pageAnchor = (pageAnchor ?? fp.currentYear) - PAGE_SIZE;
                build();
            });

            const label = document.createElement('span');

            label.textContent = `${pageStart} – ${pageEnd}`;

            const next = document.createElement('button');

            next.type = 'button';
            next.setAttribute('aria-label', 'Next years');
            next.innerHTML = svgWrapper('m9 18 6-6-6-6');
            next.disabled = pageEnd + 1 > maxYear || pageEnd + PAGE_SIZE < minYear;
            next.addEventListener('click', () => {
                pageAnchor = (pageAnchor ?? fp.currentYear) + PAGE_SIZE;
                build();
            });

            pager.append(prev, label, next);
            container.appendChild(pager);

            const cells = document.createDocumentFragment();

            for (let i = 0; i < PAGE_SIZE; i++) {
                const year = pageStart + i;
                const cell = document.createElement('span');

                cell.className = 'flatpickr-day';
                cell.textContent = String(year);
                cell.dataset.year = String(year);

                if (year === realYear) {
                    cell.classList.add('today');
                }

                if (year === startYear) {
                    cell.classList.add('selected', 'startRange');
                }

                if (year === endYear) {
                    cell.classList.add('selected', 'endRange');
                }

                if (
                    startYear !== undefined &&
                    endYear !== undefined &&
                    year > startYear &&
                    year < endYear
                ) {
                    cell.classList.add('inRange');
                }

                if (year < minYear || year > maxYear) {
                    cell.classList.add('flatpickr-disabled');
                } else {
                    cell.addEventListener('click', () => {
                        if (!isRange()) {
                            fp.setDate(new Date(year, 0, 1), true);
                            fp.close();

                            return;
                        }

                        const dates = [...fp.selectedDates];

                        // two already selected → this click restarts the range;
                        // otherwise complete (and sort) the pair. setDate fires
                        // onChange → build, so the grid re-renders either way.
                        const next =
                            dates.length === 2
                                ? [new Date(year, 0, 1)]
                                : [...dates, new Date(year, 0, 1)].sort(
                                      (a, b) => a.getTime() - b.getTime()
                                  );

                        fp.setDate(next, true);

                        if (fp.selectedDates.length === 2) {
                            fp.close();
                        }
                    });

                    // hover preview while a range start is pending
                    cell.addEventListener('mouseenter', () => {
                        if (!isRange() || fp.selectedDates.length !== 1) {
                            return;
                        }

                        clearHover();

                        const anchor = fp.selectedDates[0].getFullYear();
                        const from = Math.min(anchor, year);
                        const to = Math.max(anchor, year);

                        container.querySelectorAll<HTMLElement>('.flatpickr-day').forEach((el) => {
                            const elYear = Number(el.dataset.year);

                            if (elYear > from && elYear < to) {
                                el.classList.add('inRange');

                                hoverHighlights.push(el);
                            }
                        });
                    });
                }

                cells.appendChild(cell);
            }

            container.addEventListener('mouseleave', clearHover);
            container.appendChild(cells);
            rContainer.appendChild(container);
        };

        return {
            onReady: [
                // strip the day calendar: month select, weekday row, day grid
                () => {
                    fp.monthElements?.forEach((element) =>
                        element.parentNode?.removeChild(element)
                    );

                    if (fp.rContainer) {
                        fp.rContainer.innerHTML = '';
                    }

                    // Detach the day-grid machinery too. Otherwise setDate()'s
                    // redraw() rebuilds day cells into the now-detached days
                    // container, whose range-mode mouseover pass reads
                    // dayContainer.dateObj.getTime() — undefined — throwing and
                    // killing the rest of setDate (onChange marking, value
                    // sync) on the first year-range click. With the refs
                    // detached, buildDays() early-returns; the engine itself
                    // treats undefined as a valid post-destroy state.
                    fp.daysContainer = undefined as unknown as HTMLDivElement;
                    fp.days = undefined as unknown as HTMLDivElement;
                },
                build,
            ],
            // every selection change (pick, clear, external setDate) re-renders —
            // the page anchor is untouched by these, so the grid never shifts
            onChange: [build],
            // family-consistent with native range pickers: discard incomplete
            // ranges on close — clear() triggers onChange → build as well
            onClose: [
                () => {
                    if (isRange() && fp.selectedDates.length === 1) {
                        fp.clear();
                    }
                },
            ],
        };
    };
}

/**
 * Single composite header for the two-month layout (`showMonths: 2`): the
 * engine renders one `Month Year` block per panel, which duplicates the year
 * and off-centers the labels. This rewrites the first month block to
 * `Month 1 - Month 2` + the engine's own year input (kept visible and
 * selectable via CSS), and lets CSS hide the second block.
 *
 * Also owns the center divider element between the two day grids — see
 * ensureDivider().
 *
 * Writes are deferred to a microtask — see composite() below for why the
 * synchronous write loses to the engine's own rewrite.
 */
export function twoMonthHeaderPlugin(): FlatpickrPlugin {
    return (fp: FlatpickrInstance) => {
        // The divider overlays the gutter as a child of rContainer — NOT the
        // days strip: the engine's range-hover query
        // (`*:nth-child(-n+showMonths) > .flatpickr-day`) counts the strip's
        // element children, so an element between the dayContainers hides the
        // second grid from hover-class cleanup (stale startRange/endRange
        // pile up). rContainer is never cleared by rebuilds, so the overlay
        // also survives buildDays() wipes — kept as cheap safety regardless.
        const ensureDivider = () => {
            const host = fp.rContainer;

            if (!host || host.querySelector(':scope > .fp-months-divider')) {
                return;
            }

            const divider = document.createElement('div');

            divider.className = 'fp-months-divider';
            host.appendChild(divider);
        };

        const write = () => {
            const first = fp.monthElements?.[0];

            if (!first) {
                return;
            }

            const months = fp.l10n.months.longhand;
            const second = (fp.currentMonth + 1) % 12;

            // No year in the text — the engine's year input (right after the
            // span, kept visible) carries it: `Month 1 - Month 2 [Year]`.
            // December–January pages keep December's year, matching that input.
            first.textContent = `${months[fp.currentMonth]} - ${months[second]}`;

            ensureDivider();
        };

        // changeMonth() fires onMonthChange BEFORE its own
        // updateNavigationCurrentMonth() rewrite, so writing synchronously in
        // the hook loses the race and the engine's plain "October" lands last.
        // A microtask runs after the engine's synchronous writes, whatever
        // path triggered the change (nav arrows, redraw, setDate).
        const composite = () => queueMicrotask(write);

        return {
            onReady: [composite],
            onMonthChange: [composite],
            onYearChange: [composite],
            // setDate() can shift the view (jumpToDate) without firing
            // onMonthChange — selection changes re-composite as a safety net.
            onChange: [composite],
        };
    };
}

/**
 * Split time controls for date-time-range: the engine renders ONE shared
 * time panel that edits whichever endpoint was selected last — ambiguous
 * for a range. This hides that panel (class hook: fp-time-range-active)
 * and renders a Start/End pair of native time inputs under the grids,
 * each bound to its own endpoint.
 *
 * Input edits go through fp.setDate(dates, true) — onChange re-fires, which
 * re-syncs the inputs from the dates (setting .value dispatches no event,
 * so the round-trip terminates) and propagates the new times to the bound
 * form value.
 */
export function timeRangePlugin(): FlatpickrPlugin {
    return (fp: FlatpickrInstance) => {
        let startInput: HTMLInputElement | null = null;
        let endInput: HTMLInputElement | null = null;

        const pad = (n: number) => String(n).padStart(2, '0');
        const toTimeValue = (date?: Date) =>
            date ? `${pad(date.getHours())}:${pad(date.getMinutes())}` : '';

        /** Apply the inputs' times onto the selected endpoints. */
        const commit = () => {
            const [start, end] = fp.selectedDates;

            if (!start || !startInput || !endInput) {
                return;
            }

            const next: Date[] = [new Date(start)];
            const [startHour, startMinute] = startInput.value.split(':').map(Number);

            if (!Number.isNaN(startHour)) {
                next[0].setHours(startHour, Number.isNaN(startMinute) ? 0 : startMinute, 0, 0);
            }

            if (end) {
                const endDate = new Date(end);
                const [endHour, endMinute] = endInput.value.split(':').map(Number);

                if (!Number.isNaN(endHour)) {
                    endDate.setHours(endHour, Number.isNaN(endMinute) ? 0 : endMinute, 0, 0);
                }

                next.push(endDate);
            }

            // setDate() → jumpToDate() recenters the view on the latest
            // endpoint — keep the user's page (same principle as the year
            // grid's pageAnchor).
            const viewYear = fp.currentYear;
            const viewMonth = fp.currentMonth;

            fp.setDate(next, true);
            fp.jumpToDate(new Date(viewYear, viewMonth, 1), false);
        };

        /** Mirror the selection into the inputs — enabled once each end exists. */
        const sync = () => {
            if (!startInput || !endInput) {
                return;
            }

            const [start, end] = fp.selectedDates;

            startInput.value = toTimeValue(start);
            endInput.value = toTimeValue(end);
            startInput.disabled = !start;
            endInput.disabled = !end;
        };

        const build = () => {
            const calendar = fp.calendarContainer;

            if (!calendar || startInput) {
                return;
            }

            calendar.classList.add('fp-time-range-active');

            const row = document.createElement('div');

            row.className = 'fp-time-range';

            const makeField = (caption: string) => {
                const wrap = document.createElement('label');

                wrap.className = 'fp-time-range-field';

                const label = document.createElement('span');

                label.className = 'fp-time-range-label';
                label.textContent = caption;

                // one-liner: label + boxed input (clock icon inside the box)
                const box = document.createElement('span');

                box.className = 'fp-time-range-box';

                const input = document.createElement('input');

                input.type = 'time';
                input.disabled = true;
                input.setAttribute('aria-label', `${caption} time`);
                // isolate the input from the engine's document-level keydown
                // (arrow keys would page the calendar while typing)
                input.addEventListener('keydown', (event) => event.stopPropagation());
                input.addEventListener('change', commit);

                const icon = document.createElement('span');

                icon.className = 'fp-time-range-icon';
                icon.setAttribute('aria-hidden', 'true');
                // lucide "clock", stroke=currentColor so CSS themes it
                icon.innerHTML =
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';

                box.append(input, icon);
                wrap.append(label, box);
                row.appendChild(wrap);

                return input;
            };

            startInput = makeField('Start');
            endInput = makeField('End');

            calendar.appendChild(row);
            sync();
        };

        return {
            onReady: [build],
            onChange: [sync],
        };
    };
}
