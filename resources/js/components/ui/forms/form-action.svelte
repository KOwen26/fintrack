<script lang="ts">
    import type { useForm } from '@inertiajs/svelte';
    import type { RestProps } from '@type/index';

    import { cn } from '@utilities/shadcn';

    import Button from '@components/ui/button.svelte';
    import SubmitButton from '@components/ui/forms/submit-button.svelte';

    interface Props extends RestProps {
        form: ReturnType<typeof useForm>;

        formId?: string;
        onSubmit?: (e: MouseEvent) => void;
        labelSubmit?: string;

        onCancel?: (e: MouseEvent) => void;
        withoutCancel?: boolean;
        labelCancel?: string;

        cancelClass?: string;
        submitClass?: string;
    }

    let {
        formId,
        form,
        onSubmit,
        labelSubmit = 'Simpan',
        onCancel = () => {
            window.history.back();
        },
        withoutCancel = false,
        labelCancel = 'Batal',
        cancelClass,
        submitClass,
        class: _class,
        ...props
    }: Props = $props();
</script>

<div class={cn('flex flex-row flex-wrap justify-end gap-3', _class)}>
    {#if !withoutCancel}
        <Button
            class={cn('min-w-25', cancelClass)}
            color="secondary"
            onclick={onCancel}
            variant="outline">
            {labelCancel}
        </Button>
    {/if}

    <SubmitButton
        class={cn('min-w-25', submitClass)}
        form={formId}
        onclick={onSubmit}
        submitting={form.processing}>
        {#snippet icon()}
            <i class="iconify solar--add-square-bold-duotone"></i>
        {/snippet}

        {labelSubmit}
    </SubmitButton>
</div>
