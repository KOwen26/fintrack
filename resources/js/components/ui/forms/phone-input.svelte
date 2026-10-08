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
    import { inputGroupClasses } from './input.svelte';
    import MaskedInput from './masked-input.svelte';

    import { cn } from '@utilities/shadcn.js';

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

<div class={cn(inputGroupClasses, 'tabular-nums')}>
    <div class="relative flex h-full w-[7ch] items-center border-r border-input">
        <span class="ps-3">+</span>
        <input
            name={phoneCodeName}
            class="w-[3ch] bg-transparent text-base outline-none"
            maxlength="3"
            readonly={!isPhoneCodeEditable}
            tabindex={!isPhoneCodeEditable ? -1 : 0}
            type="phone"
            bind:value={phoneCode} />
    </div>
    <MaskedInput
        {name}
        defaultClass="h-full w-full bg-transparent px-3 text-base outline-none md:text-sm"
        maskPreset="phone_number"
        bind:value={phone} />
</div>
