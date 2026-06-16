<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import RecurringPresetsController from '@wayfinder/App/Http/Controllers/RecurringPresetsController';

    import RecurringFrequencyBadge from '@components/module/recurring-preset/recurring-frequency-badge.svelte';
    import RecurringPresetForm from '@components/module/recurring-preset/recurring-preset-form.svelte';
    import PresetTypeBadge from '@components/module/transaction-preset/preset-type-badge.svelte';
    import Badge from '@components/ui/badge.svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';

    interface Props {
        presets: App.Models.TransactionRecurringPreset[];
        accounts: App.Models.Account[];
        categories: App.Models.Category[];
    }

    let { presets, accounts, categories }: Props = $props();

    let showCreateForm = $state(false);
    let editingPreset = $state<App.Models.TransactionRecurringPreset | null>(null);
    let deletingPresetId = $state<number | null>(null);

    const deletingPreset = $derived(
        deletingPresetId !== null ? presets.find((p) => p.id === deletingPresetId) : null
    );

    function toggle(preset: App.Models.TransactionRecurringPreset): void {
        router.post(
            RecurringPresetsController.toggle.url({ preset: preset.id }),
            {},
            {
                preserveScroll: true,
            }
        );
    }

    function destroy(): void {
        if (!deletingPresetId) {
            return;
        }
        router.delete(RecurringPresetsController.destroy.url({ preset: deletingPresetId }), {
            onFinish: () => (deletingPresetId = null),
        });
    }

    function formatDate(dateStr: string | null): string {
        if (!dateStr) {
            return '—';
        }

        return new Date(dateStr).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    }
</script>

<div class="p-4">
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-bold">Recurring Rules</h1>
        <Button
            color="primary"
            onclick={() => {
                showCreateForm = !showCreateForm;
                editingPreset = null;
            }}
            size="sm">
            <i class="iconify size-4 ph--plus-bold"></i>
            Add
        </Button>
    </div>

    {#if showCreateForm}
        <div class="mb-4">
            <RecurringPresetForm
                {accounts}
                {categories}
                onCancel={() => (showCreateForm = false)}
                onSuccess={() => (showCreateForm = false)} />
        </div>
    {/if}

    {#if editingPreset}
        <div class="mb-4">
            <div class="mb-2 flex items-center justify-between">
                <p class="text-sm font-medium text-base-content/60">
                    Editing: {editingPreset.name}
                </p>
                <Button
                    color="light"
                    onclick={() => (editingPreset = null)}
                    size="sm"
                    variant="ghost">
                    Cancel
                </Button>
            </div>
            <RecurringPresetForm
                {accounts}
                {categories}
                onCancel={() => (editingPreset = null)}
                onSuccess={() => (editingPreset = null)}
                preset={editingPreset} />
        </div>
    {/if}

    {#if presets.length === 0}
        <div class="flex flex-col items-center justify-center py-16 text-base-content/50">
            <i class="iconify mb-3 size-12 ph--clock-countdown-bold"></i>
            <p class="text-sm">No recurring rules yet</p>
            <p class="mt-1 text-xs text-base-content/40">
                Set up rent, salary, and subscriptions once
            </p>
            <Button class="mt-4" color="primary" onclick={() => (showCreateForm = true)} size="sm">
                Create your first rule
            </Button>
        </div>
    {:else}
        <div class="space-y-3">
            {#each presets as preset (preset.id)}
                <Card wrapperClass={preset.is_active ? '' : 'opacity-60'}>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1 space-y-1">
                            <div class="flex items-center gap-2">
                                <p class="truncate font-semibold">{preset.name}</p>
                                {#if !preset.is_active}
                                    <Badge color="light" variant="outline">Paused</Badge>
                                {/if}
                            </div>
                            <div class="flex flex-wrap items-center gap-1">
                                <PresetTypeBadge type={preset.type} />
                                <RecurringFrequencyBadge frequency={preset.frequency} />
                                <span class="text-xs text-base-content/50">
                                    {Number(preset.amount).toLocaleString('id-ID')}
                                </span>
                            </div>
                            <div class="flex items-center gap-3 text-xs text-base-content/50">
                                <span>Next: {formatDate(preset.next_run_date)}</span>
                                {#if preset.last_run_date}
                                    <span>Last: {formatDate(preset.last_run_date)}</span>
                                {/if}
                                {#if preset.recurrence_end_date}
                                    <span>Ends: {formatDate(preset.recurrence_end_date)}</span>
                                {/if}
                            </div>
                            {#if preset.account}
                                <p class="text-xs text-base-content/40">{preset.account.name}</p>
                            {/if}
                        </div>

                        <div class="flex shrink-0 items-center gap-1">
                            <!-- Toggle active/paused -->
                            <Button
                                class="btn-circle btn-sm"
                                color={preset.is_active ? 'warning' : 'success'}
                                onclick={() => toggle(preset)}
                                title={preset.is_active ? 'Pause' : 'Activate'}
                                variant="ghost">
                                <i
                                    class="iconify size-4 {preset.is_active
                                        ? 'ph--pause-bold'
                                        : 'ph--play-bold'}"></i>
                            </Button>
                            <Button
                                class="btn-circle btn-sm"
                                color="light"
                                onclick={() => {
                                    editingPreset = preset;
                                    showCreateForm = false;
                                }}
                                variant="ghost">
                                <i class="iconify size-4 ph--pencil-simple-bold"></i>
                            </Button>
                            <Button
                                class="btn-circle btn-sm"
                                color="error"
                                onclick={() => (deletingPresetId = preset.id)}
                                variant="ghost">
                                <i class="iconify size-4 ph--trash-bold"></i>
                            </Button>
                        </div>
                    </div>
                </Card>
            {/each}
        </div>
    {/if}
</div>

<ConfirmationModal
    cancelText="Cancel"
    confirmButtonProps={{ color: 'error' }}
    confirmText="Delete"
    onCancel={() => (deletingPresetId = null)}
    onConfirm={destroy}
    title="Delete Recurring Rule"
    bind:open={deletingPresetId}>
    {#if deletingPreset}
        Delete <strong>{deletingPreset.name}</strong>? Future transactions will no longer be
        generated.
    {/if}
</ConfirmationModal>
