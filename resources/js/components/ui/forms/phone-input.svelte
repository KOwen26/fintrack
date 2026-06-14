<script lang="ts" module>
    import type { RestProps } from '@type/index';

    export type PhoneInputProps = {
        phone: string;
        phoneCode?: string;
        isPhoneCodeEditable?: boolean;
        phoneCodeName?: string;
        name?: string;
    };
</script>

<script lang="ts">
    import MaskedInput from './masked-input.svelte';

    let {
        phone = $bindable(),
        phoneCode = $bindable('62'),
        isPhoneCodeEditable = false,
        phoneCodeName = 'phone_code',
        name = 'phone_number',
        ...props
    }: PhoneInputProps & RestProps = $props();

    if (!isPhoneCodeEditable) phoneCode = '62';
</script>

<div class="input overflow-clip tabular-nums">
    <div class="relative h-full w-[7ch] border-r border-[var(--input-color)]">
        <span>+</span>
        <input
            name={phoneCodeName}
            class="w-[3ch]"
            maxlength="3"
            readonly={!isPhoneCodeEditable}
            tabindex={!isPhoneCodeEditable ? -1 : 0}
            type="phone"
            bind:value={phoneCode} />
    </div>
    <MaskedInput {name} defaultClass="" maskPreset="phone_number" bind:value={phone} />
</div>
