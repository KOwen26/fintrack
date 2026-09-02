<script lang="ts">
    import { useForm } from '@inertiajs/svelte';

    import { setBreadcrumbItems } from '@utilities/global-states.svelte';
    import { showToast } from '@utilities/helper.svelte';

    import Button from '@components/ui/button.svelte';
    import SubmitButton from '@components/ui/forms/submit-button.svelte';
    import Modal from '@components/ui/modal.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';

    const form = useForm({
        name: '',
        phone: '',
        password: '',
        gender: '',
        address: '',
        birthdate: '',
        subscribe: false,
        education: '',
    });

    let openModal = $state(false);
    let openCustomizedModal = $state(false);
    let openConfirmationModal = $state(false);
    let openNestedModal = $state(false);

    setBreadcrumbItems([{ title: 'Dev' }, { title: 'Design' }]);
</script>

<div class="space-y-8">
    <h1>Design System</h1>
    <div class="space-y-6">
        <section>
            <h2>Modal</h2>
            <hr class="mt-2 mb-6" />
            <div class="flex gap-5">
                <Button onclick={() => (openModal = !openModal)}>Modal</Button>
                <Button
                    color="secondary"
                    onclick={() => (openConfirmationModal = !openConfirmationModal)}>
                    Confirmation Modal</Button>
                <Button color="accent" onclick={() => (openCustomizedModal = !openCustomizedModal)}>
                    Customized Modal (See Code)</Button>
            </div>
        </section>
        <section>
            <h2>Toast</h2>
            <hr class="mt-2 mb-6" />
            <div class="flex gap-5">
                <Button
                    color="success"
                    onclick={() => showToast({ type: 'success', message: 'Success message' })}
                    >Success</Button>
                <Button
                    color="error"
                    onclick={() => showToast({ type: 'error', message: 'Error message' })}
                    >Error</Button>
            </div>
        </section>
        <section>
            <h2>Icons</h2>
            <hr class="mt-2 mb-6" />
            <div class="grid grid-cols-2 gap-5">
                <div>
                    <div>
                        <Button href="https://phosphoricons.com/" variant="link" withoutInertia
                            >Iconify - Phosphor Icons</Button>
                    </div>
                    <div><i class="iconify solar--database-line-duotone"></i></div>
                </div>
                <div>
                    <div>
                        <Button
                            color="secondary"
                            href="https://icon-sets.iconify.design/solar/page-1.html"
                            variant="link"
                            withoutInertia>Iconify - Solar Icons</Button>
                    </div>
                    <div><i class="iconify solar--widget-5-line-duotone"></i></div>
                </div>
            </div>
        </section>
    </div>
</div>

<Modal title="Modal Title" bind:open={openModal}>
    <p>This is a modal description.</p>
    <Button onclick={() => (openNestedModal = !openNestedModal)}>Open Modal</Button>
</Modal>

<Modal title="Nested Modal Title" bind:open={openNestedModal}>
    <p>This is a modal description.</p>
</Modal>

<ConfirmationModal title="Hapus Data" bind:open={openConfirmationModal}>
    <form id="modal_form" action="">Apakah Anda yakin ingin menghapus data ini?</form>
    {#snippet actionButton()}
        <SubmitButton form="modal_form" submitting={form.processing}>Simpan</SubmitButton>
    {/snippet}
</ConfirmationModal>

<Modal title="Modal Title" bind:open={openCustomizedModal}>
    {#snippet header(closeButton)}
        <div class="bg-primary p-5 text-white">Customized Header</div>
    {/snippet}
    <p>This is a modal description.</p>
    {#snippet footer(closeButton)}
        <div class="bg-primary p-5 text-white">Customized Footer</div>
    {/snippet}
</Modal>
