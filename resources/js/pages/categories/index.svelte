<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { getDecorationColor } from '@data/decoration-colors';
    import { router } from '@inertiajs/svelte';
    import CategoryController from '@wayfinder/App/Http/Controllers/CategoryController';

    import CategoryForm from '@components/module/category/category-form.svelte';
    import Badge from '@components/ui/badge.svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';

    let { categories }: { categories: App.Models.Category[] } = $props();

    let showForm = $state(false);
    let deletingId = $state<number | null>(null);
    let showDeleteConfirm = $state(false);

    function confirmDelete(id: number) {
        deletingId = id;
        showDeleteConfirm = true;
    }

    function cancelDelete() {
        deletingId = null;
        showDeleteConfirm = false;
    }

    function destroy() {
        if (!deletingId) return;
        router.delete(CategoryController.destroy.url({ category: deletingId }), {
            onFinish: () => {
                deletingId = null;
                showDeleteConfirm = false;
            },
        });
    }
</script>

<div class="p-4">
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-bold">Categories</h1>
        <Button color="primary" onclick={() => (showForm = !showForm)} size="sm">
            <i class="iconify size-4 ph--plus-bold"></i>
            Add
        </Button>
    </div>

    {#if showForm}
        <CategoryForm
            {categories}
            onCancel={() => (showForm = false)}
            onSuccess={() => (showForm = false)} />
    {/if}

    <div class="space-y-3">
        {#each categories as group (group.id)}
            <Card>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span
                            style="background-color: {getDecorationColor(group.decorations?.color)
                                ?.value}"
                            class="inline-block h-3 w-3 rounded-full"></span>
                        <span class="font-semibold text-sm">{group.name}</span>
                        {#if group.is_fixed_cost}
                            <Badge color="light" variant="outline">Fixed</Badge>
                        {/if}
                    </div>
                    <Button
                        class="btn-xs"
                        color="error"
                        onclick={() => confirmDelete(group.id)}
                        variant="ghost">
                        <i class="iconify size-4 ph--trash-bold"></i>
                    </Button>
                </div>

                {#if group.children?.length}
                    <ul class="ml-5 mt-2 space-y-1 border-t border-base-200 pt-2">
                        {#each group.children as child (child.id)}
                            <li class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span
                                        style="background-color: {getDecorationColor(
                                            child.decorations?.color
                                        )?.value}"
                                        class="inline-block h-2 w-2 rounded-full"></span>
                                    <span class="text-sm">{child.name}</span>
                                    {#if child.is_fixed_cost}
                                        <Badge color="light" variant="outline">Fixed</Badge>
                                    {/if}
                                </div>
                                <Button
                                    class="btn-xs"
                                    color="error"
                                    onclick={() => confirmDelete(child.id)}
                                    variant="ghost">
                                    <i class="iconify size-4 ph--trash-bold"></i>
                                </Button>
                            </li>
                        {/each}
                    </ul>
                {/if}
            </Card>
        {/each}
    </div>
</div>

<ConfirmationModal
    confirmButtonProps={{ color: 'error' }}
    confirmText="Delete"
    onCancel={cancelDelete}
    onConfirm={destroy}
    title="Delete Category"
    bind:open={showDeleteConfirm}>
    This category will be soft-deleted. Transactions using it are unaffected.
</ConfirmationModal>
