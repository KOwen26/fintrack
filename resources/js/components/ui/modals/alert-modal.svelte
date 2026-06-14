<script lang="ts" module>
    import type { RestProps } from '@type/index';
    import type { AlertDialog as AlertDialogPrimitive, WithoutChild } from 'bits-ui';
    import type { Snippet } from 'svelte';

    export interface AlertModalProps
        extends RestProps, Omit<AlertDialogPrimitive.RootProps, 'children'> {
        open: boolean;
        title?: string;
        cancelText?: string;
        contentProps?: WithoutChild<AlertDialogPrimitive.ContentProps>;
        header?: Snippet;
        footer?: Snippet<[cancelButton: Snippet]>;
        actionButton?: Snippet;
    }
</script>

<script lang="ts">
    import {
        AlertDialog,
        AlertDialogAction,
        AlertDialogCancel,
        AlertDialogContent,
        AlertDialogFooter,
        AlertDialogHeader,
        AlertDialogTitle,
    } from '@components/ui/atoms/alert-dialog';

    let {
        open = $bindable(false),
        title = '',
        cancelText = 'Close',
        contentProps,
        header = defaultHeader,
        footer = defaultFooter,
        actionButton = defaultActionButton,
        children,
        ...props
    }: AlertModalProps = $props();
</script>

<AlertDialog bind:open {...props}>
    <AlertDialogContent {...contentProps}>
        <AlertDialogHeader>
            <AlertDialogTitle>
                {@render header?.()}
            </AlertDialogTitle>
        </AlertDialogHeader>

        {#if children}
            <div class={['text-muted-foreground text-sm', props.class]}>
                {@render children?.()}
            </div>
        {/if}

        <AlertDialogFooter>
            {@render footer?.(footerCancelButton)}
        </AlertDialogFooter>
    </AlertDialogContent>
</AlertDialog>

{#snippet footerCancelButton()}
    <AlertDialogCancel>{cancelText}</AlertDialogCancel>
{/snippet}

{#snippet defaultHeader()}
    {title}
{/snippet}

{#snippet defaultFooter(cancelButton: Snippet)}
    {@render cancelButton?.()}
    {@render actionButton?.()}
{/snippet}

{#snippet defaultActionButton()}
    <AlertDialogAction>Continue</AlertDialogAction>
{/snippet}
