/**
 * Reusable Reactive States
 *
 * @reference https://svelte.dev/docs/svelte/svelte-js-files
 * */

export const sidebar = $state({
    is_collapsed: localStorage.getItem('sidebar-collapse') === 'true' || false,
    collapse() {
        this.is_collapsed = !this.is_collapsed;
        localStorage.setItem('sidebar-collapse', this.is_collapsed.toString());
    },
});
