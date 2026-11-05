<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { useForm } from '@inertiajs/svelte';
    import ImportController from '@wayfinder/App/Http/Controllers/ImportController';

    import Button from '@components/ui/button.svelte';
    import DrawerModal from '@components/ui/drawer-modal.svelte';
    import Field from '@components/ui/forms/field.svelte';
    import FileInput from '@components/ui/forms/file-input.svelte';

    let {
        account,
        open = $bindable(false),
    }: {
        account: App.Models.Account;
        open?: boolean;
    } = $props();

    let form = useForm<{ statement: File | null }>({ statement: null });
    let files = $state<FileList | null>(null);

    function submit(): void {
        form.statement = files?.item(0) ?? null;

        form.post(ImportController.store.url({ account: account.id }), {
            preserveScroll: true,
        });
    }
</script>

<DrawerModal title="Import Statement" bind:open>
    <Field error={form.errors.statement} title="Statement XLSX">
        <FileInput accept=".csv,.xlsx" bind:files />
    </Field>

    <Button
        class="w-full"
        color="primary"
        disabled={files === null || files.length === 0 || form.processing}
        onclick={submit}>
        {form.processing ? 'Parsing…' : 'Parse Statement'}
        <i class="iconify size-5 solar--upload-square-bold-duotone"></i>
    </Button>
</DrawerModal>
