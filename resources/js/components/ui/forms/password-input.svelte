<script lang="ts" module>
    export type PasswordInputProps = {
        value: string;
        name?: string;
    };
</script>

<script lang="ts">
    import Input from './input.svelte';

    let passwordToggle: boolean = $state(false);

    let { name = 'password', value = $bindable() }: PasswordInputProps = $props();
</script>

<div class="relative w-full">
    <Input {name} class="pe-12" type={passwordToggle ? 'text' : 'password'} bind:value />
    <button
        class="absolute inset-y-0 inset-e-0 flex w-12 cursor-pointer items-center justify-center text-muted-foreground hover:text-foreground"
        aria-label={passwordToggle ? 'Hide password' : 'Show password'}
        onclick={() => (passwordToggle = !passwordToggle)}
        type="button">
        <div class="size-5">
            <i class="iconify -scale-x-100 tabler--eye-off" class:hidden={passwordToggle}> </i>
            <i
                class="iconify tabler--eye"
                class:block={passwordToggle}
                class:hidden={!passwordToggle}>
            </i>
        </div>
    </button>
</div>
