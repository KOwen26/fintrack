<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import TransactionPresetsController from '@wayfinder/App/Http/Controllers/TransactionPresetsController';

    import PresetForm from '@components/module/transaction-preset/preset-form.svelte';
    import PresetTypeBadge from '@components/module/transaction-preset/preset-type-badge.svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';

    interface Props {
        presets: App.Models.TransactionPreset[];
        accounts: App.Models.Account[];
        categories: App.Models.Category[];
    }

    let { presets, accounts, categories }: Props = $props();

    let showCreateForm = $state(false);
    let editingPreset = $state<App.Models.TransactionPreset | null>(null);
    let deletingPresetId = $state<number | null>(null);

    const deletingPreset = $derived(
        deletingPresetId !== null ? presets.find((p) => p.id === deletingPresetId) : null
    );

    function destroy(): void {
        if (!deletingPresetId) {
            return;
        }
        router.delete(TransactionPresetsController.destroy.url({ preset: deletingPresetId }), {
            onFinish: () => (deletingPresetId = null),
        });
    }
</script>

<div class="p-4">
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-bold">Templates</h1>
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
            <PresetForm
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
            <PresetForm
                {accounts}
                {categories}
                onCancel={() => (editingPreset = null)}
                onSuccess={() => (editingPreset = null)}
                preset={editingPreset} />
        </div>
    {/if}

    {#if presets.length === 0}
        <div class="flex flex-col items-center justify-center py-16 text-base-content/50">
            <i class="iconify mb-3 size-12 ph--lightning-bold"></i>
            <p class="text-sm">No templates yet</p>
            <p class="mt-1 text-xs text-base-content/40">Templates pre-fill the quick-add form</p>
            <Button class="mt-4" color="primary" onclick={() => (showCreateForm = true)} size="sm">
                Create your first template
            </Button>
        </div>
    {:else}
        <div class="space-y-3">
            {#each presets as preset (preset.id)}
                <Card>
                    <div class="flex items-center justify-between">
                        <div class="space-y-1">
                            <p class="font-semibold">{preset.name}</p>
                            <div class="flex items-center gap-1">
                                <PresetTypeBadge type={preset.type} />
                                {#if preset.default_amount != null}
                                    <span class="text-xs text-base-content/50">
                                        {Number(preset.default_amount).toLocaleString('id-ID')}
                                    </span>
                                {/if}
                            </div>
                            {#if preset.default_description}
                                <p class="text-xs text-base-content/50">
                                    {preset.default_description}
                                </p>
                            {/if}
                        </div>
                        <div class="flex items-center gap-1">
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
    title="Delete Template"
    bind:open={deletingPresetId !== null}>
    {#if deletingPreset}
        Delete <strong>{deletingPreset.name}</strong>? This cannot be undone.
    {/if}
</ConfirmationModal>
