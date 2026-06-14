<script lang="ts" module>
    import type { RestProps } from '@type/index';
    import type { WithoutChild } from 'bits-ui';
    import type { Snippet } from 'svelte';

    export interface ModalProps extends RestProps, Omit<Dialog.RootProps, 'children'> {
        open: boolean;
        title?: string;
        contentProps?: WithoutChild<Dialog.ContentProps>;
        header?: Snippet<[closeButton: Snippet]>;
        footer?: Snippet<[closeButton: Snippet]>;
        actionButton?: Snippet;

        formId?: string;
        formSubmitting?: boolean;
    }
</script>

<script lang="ts">
    import { Dialog } from 'bits-ui';

    import Button from '@components/ui/button.svelte';
    import SubmitButton from '@components/ui/forms/submit-button.svelte';

    let {
        open = $bindable(false),
        title = '',
        contentProps,
        header = defaultHeader,
        footer = defaultFooter,
        actionButton = defaultActionButton,
        children,
        formId = undefined,
        formSubmitting = undefined,
        ...props
    }: ModalProps = $props();
</script>

<Dialog.Root bind:open {...props}>
    <Dialog.Portal>
        <Dialog.Overlay
            class={[
                'fixed inset-0 z-50 bg-black/75',
                'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0',
            ]} />
        <Dialog.Content
            class={[
                'fixed top-[50%] left-[50%] z-50',
                'shadow-popover w-full max-w-[calc(100%-2rem)] translate-x-[-50%] translate-y-[-50%] overflow-clip rounded-xl bg-white outline-hidden sm:max-w-[480px] md:w-full',
                'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95',
            ]}
            {...contentProps}>
            <Dialog.Title>
                {@render header?.(headerCloseButton)}
            </Dialog.Title>
            <div class={['overflow-y-auto px-5', props.class]}>
                {@render children?.()}
            </div>
            {@render footer?.(footerCloseButton)}
        </Dialog.Content>
    </Dialog.Portal>
</Dialog.Root>

{#snippet footerCloseButton()}
    <Dialog.Close>
        <Button color="light" variant="outline">Tutup</Button>
    </Dialog.Close>
{/snippet}

{#snippet headerCloseButton()}
    <Dialog.Close>
        <Button class="btn-square" color="light" variant="outline">
            <i class="iconify ph--x-bold"></i>
        </Button>
    </Dialog.Close>
{/snippet}

{#snippet defaultHeader(closeButton: Snippet)}
    <div class="flex items-center justify-between gap-5 p-5">
        <h2 class="text-lg font-semibold">
            {title}
        </h2>
        {@render closeButton?.()}
    </div>
{/snippet}

{#snippet defaultFooter(closeButton: Snippet)}
    <div class="flex w-full justify-end gap-3 p-5 px-5">
        {@render closeButton?.()}

        {#if formId}
            {@render actionButton?.()}
        {/if}
    </div>
{/snippet}

{#snippet defaultActionButton()}
    <SubmitButton form={formId} submitting={formSubmitting}>Simpan</SubmitButton>
{/snippet}
