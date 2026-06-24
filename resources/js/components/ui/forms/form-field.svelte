<script lang="ts" module>
    import type { RestProps } from '@type/index';
    import type { Snippet } from 'svelte';

    type FieldDescriptionProps = { description: string };
    type FieldErrorProps = { errors: { message?: string }[] };
    type FieldLabelProps = { label: string };

    export type FieldProps = {
        label: FieldLabelProps['label'];
        description?: FieldDescriptionProps['description'];
        errors?: FieldErrorProps['errors'];

        fieldLabel?: Snippet<[FieldLabelProps]>;
        fieldDescription?: Snippet<[FieldDescriptionProps]>;
        fieldError?: Snippet<[FieldErrorProps]>;
    };
</script>

<script lang="ts">
    import { cn } from '@utilities/shadcn.js';

    let {
        label,
        description,
        errors,

        fieldDescription = fieldDescriptionSnippet,
        fieldError = fieldErrorSnippet,
        fieldLabel = fieldLabelSnippet,
        class: className,
        children,

        ...restProps
    }: FieldProps & RestProps = $props();

    function getFieldError(errors: { message?: string }[], children: Snippet | undefined) {
        const hasContent = (() => {
            if (children) return true;
            if (!errors) return false;
            if (errors.length === 1 && !errors[0]?.message) return false;

            return true;
        })();

        const isMultipleErrors = errors && errors.length > 1;
        const singleErrorMessage = errors && errors.length === 1 && errors[0]?.message;

        return {
            hasContent,
            isMultipleErrors,
            singleErrorMessage,
        };
    }
</script>

<fieldset
    data-slot="field-set"
    class={cn(
        'flex flex-col gap-6',
        'has-[>[data-slot=checkbox-group]]:gap-3 has-[>[data-slot=radio-group]]:gap-3',
        className
    )}
    {...restProps}>
    {@render fieldLabel({ label })}
    {@render children?.()}
    {@render fieldDescription({ description })}
    {@render fieldError({ errors })}
</fieldset>

{#snippet fieldLabelSnippet({ label })}
    <label
        data-slot="field-label"
        class={cn(
            'group/field-label peer/field-label flex w-fit gap-2 leading-snug group-data-[disabled=true]/field:opacity-50',
            'has-[>[data-slot=field]]:w-full has-[>[data-slot=field]]:flex-col has-[>[data-slot=field]]:rounded-md has-[>[data-slot=field]]:border [&>*]:data-[slot=field]:p-4',
            'has-data-[state=checked]:bg-primary/5 has-data-[state=checked]:border-primary dark:has-data-[state=checked]:bg-primary/10',
            className
        )}
        {...restProps}>
        {label}
    </label>
{/snippet}

{#snippet fieldDescriptionSnippet({ description })}
    <p
        data-slot="field-description"
        class={cn(
            'text-muted-foreground text-sm leading-normal font-normal group-has-[[data-orientation=horizontal]]/field:text-balance',
            'last:mt-0 nth-last-2:-mt-1 [[data-variant=legend]+&]:-mt-1.5',
            '[&>a:hover]:text-primary [&>a]:underline [&>a]:underline-offset-4',
            className
        )}
        {...restProps}>
        {description}
    </p>
{/snippet}

{#snippet fieldErrorSnippet({ errors })}
    {@const { hasContent, isMultipleErrors, singleErrorMessage } = getFieldError(errors, undefined)}

    {#if hasContent}
        <div
            data-slot="field-error"
            class={cn('text-destructive text-sm font-normal', className)}
            role="alert"
            {...restProps}>
            {#if children}
                {@render children()}
            {:else if singleErrorMessage}
                {singleErrorMessage}
            {:else if isMultipleErrors}
                <ul class="ml-4 flex list-disc flex-col gap-1">
                    {#each errors ?? [] as error, index (index)}
                        {#if error?.message}
                            <li>{error.message}</li>
                        {/if}
                    {/each}
                </ul>
            {/if}
        </div>
    {/if}
{/snippet}
