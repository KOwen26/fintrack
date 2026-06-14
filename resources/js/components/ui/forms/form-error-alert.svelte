<script generics="TForm extends Record<string, any>" lang="ts">
    import type { InertiaFormProps } from '@inertiajs/svelte';

    import Alert from '@components/ui/alert.svelte';

    interface Props {
        errors: InertiaFormProps<TForm>['errors'];
        title?: string;
    }

    let { errors, title = 'Terjadi Kesalahan Pada Validasi' }: Props = $props();
</script>

{#if Object.keys(errors).length > 0}
    <Alert color="error" {title} variant="outline">
        <ul class="list-inside list-disc space-y-1 ps-2">
            {#each Object.entries(errors) as [key, value], i (`${key}-${i}`)}
                <li>
                    {value}
                </li>
            {/each}
        </ul>
    </Alert>
{/if}
