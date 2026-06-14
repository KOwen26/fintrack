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

<div class="input flex w-full items-center overflow-clip">
    <Input {name} type={passwordToggle ? 'text' : 'password'} bind:value />
    <button
        class="group hover:bg-base-300/60 -mx-3 cursor-pointer border-l border-[var(--input-color)] px-3 py-2.5"
        aria-label="password"
        onclick={() => (passwordToggle = !passwordToggle)}
        type="button">
        <div class="size-5">
            <i class="iconify ph--eye-slash-bold" class:hidden={passwordToggle}> </i>
            <i
                class="iconify ph--eye-bold"
                class:block={passwordToggle}
                class:hidden={!passwordToggle}>
            </i>
        </div>
    </button>
</div>
