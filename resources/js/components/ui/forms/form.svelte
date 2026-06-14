<script generics="TForm extends object" lang="ts">
    import type { InertiaForm, InertiaFormProps } from '@inertiajs/svelte';
    import type { RestProps } from '@type/index';
    import type { HTMLFormAttributes } from 'svelte/elements';

    type SubmitOptions = Parameters<InertiaFormProps<TForm>['submit']>[2];

    interface FormProps {
        /**
         * The Inertia form object returned by `useForm`.
         */
        form: InertiaForm<TForm>;
        // form: ReturnType<typeof useForm>;
        /**
         * The HTTP method to use for the form submission.
         * Defaults to "post".
         */
        method?: 'get' | 'post' | 'put' | 'patch' | 'delete';
        /**
         * The URL to submit the form to.
         * Required if `onsubmit` is not provided and you want the component to handle the submission.
         */
        // action?: string; // disabled because already provided in HTMLFormAttributes
        /**
         * Custom submit handler.
         * If provided, this function will be called instead of the default submission logic.
         */
        onsubmit?: HTMLFormAttributes['onsubmit'];
        submitOptions?: SubmitOptions;
        children?: RestProps['children'];
    }

    interface FinalFormProps extends FormProps, Omit<HTMLFormAttributes, 'children' | 'method'> {}

    let {
        form,
        method = 'post',
        action,
        onsubmit,
        submitOptions: _submitOptions,
        children,
        ...props
    }: FinalFormProps = $props();

    let submitOptions: SubmitOptions = $derived({
        preserveScroll: true,
        ..._submitOptions,
    });

    function handleSubmit(e: Parameters<HTMLFormAttributes['onsubmit']>[0]) {
        if (!action && !onsubmit) {
            throw new Error(
                'Form component requires either an `action` prop or an `onsubmit` handler.'
            );
        }

        // If a custom onsubmit handler is provided, use it.
        if (onsubmit) {
            e.preventDefault();
            onsubmit(e);

            return;
        }

        // TODO: When the useForm method updated into Svelte 5, remove the form store
        // If we have a form object and an action, handle the Inertia submission.
        if (form && action) {
            e.preventDefault();

            form.submit(
                props?.enctype === 'multipart/form-data' ? 'post' : method,
                action,
                submitOptions
            );
        }
    }
</script>

<form onsubmit={handleSubmit} {...props}>
    <input name="_method" type="hidden" value={method} />
    {@render children?.()}
</form>

<!--
    @component

    ## Form
    A wrapper around `<form>` that integrates seamlessly with **Inertia.js Svelte**'s `useForm`.
    It handles method spoofing (for PUT/PATCH/DELETE), preserves scroll by default, and supports both Inertia submissions and custom submit handlers.

    ## Props
    @prop form — The Inertia form object returned by `useForm<TForm>()`. Required for Inertia submissions.
    @prop method = "post" — HTTP method: `'get' | 'post' | 'put' | 'patch' | 'delete'`. Spoofed with `_method` hidden input when needed.
    @prop action — The URL to submit to (required if no `onsubmit` handler is provided and Inertia submission is used).
    @prop onsubmit — Optional custom submit handler. If provided, the component will not handle Inertia submission.
    @prop submitOptions — Additional options passed to `form.submit()` (e.g., `preserveState`, `onSuccess`, etc.). Defaults include `{ preserveScroll: true }`.
    @prop children — Snippet containing the form fields and buttons.
    @prop ...props — All other native `<form>` attributes (e.g., `enctype`, `autocomplete`, etc.).

    ## Example
    ```svelte
    <script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import Form from '$lib/components/Form.svelte';

    const form = useForm({ name: '', email: '' });
    </script>

    <Form {form} action="/users" method="post">
    {#snippet children()}
        <input bind:value={form.name} name="name" />
        <input bind:value={form.email} name="email" />

        <button type="submit" disabled={form.processing}>
        {form.processing ? 'Saving...' : 'Save'}
        </button>
    {/snippet}
    </Form>
    ```
-->
