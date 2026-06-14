<script lang="ts">
    import { router, useForm } from '@inertiajs/svelte';
    import { SvelteDate } from 'svelte/reactivity';

    import { showToast } from '@utilities/helper.svelte';

    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import CheckboxGroup from '@components/ui/forms/checkbox-group.svelte';
    import Checkbox from '@components/ui/forms/checkbox.svelte';
    import DateInput from '@components/ui/forms/date-input.svelte';
    import Field from '@components/ui/forms/field.svelte';
    import FileInput from '@components/ui/forms/file-input.svelte';
    import Input from '@components/ui/forms/input.svelte';
    import MaskedInput from '@components/ui/forms/masked-input.svelte';
    import PasswordInput from '@components/ui/forms/password-input.svelte';
    import PhoneInput from '@components/ui/forms/phone-input.svelte';
    import RadioItem from '@components/ui/forms/radio-group-item.svelte';
    import RadioGroup from '@components/ui/forms/radio-group.svelte';
    import Select from '@components/ui/forms/select.svelte';
    import SubmitButton from '@components/ui/forms/submit-button.svelte';
    import Switch from '@components/ui/forms/switch.svelte';
    import Textarea from '@components/ui/forms/textarea.svelte';

    const form = useForm({
        email: '',
        password: '',
        phone: '',
        date_input: new Date('2025-03-15'),
        date_time_input: new Date(),
        month_input: new Date('2024-12'),
        phone_code_input: '',
        select_basic_input: '',
        select_input: '',
        select_advance_input: '',
        country_select: '',
        file_upload: null,
        checkbox_group_input: [],
        checkbox_input: false,
        switch_input: false,
        radio_input: 'A',
        textarea_input: '',
        masked_input: '',
        masked_value_input: '',
    });

    const onsubmit = (event: SubmitEvent) => {
        event.preventDefault();
        console.log({ $form });

        $form
            .transform((data) => ({
                ...data,
                file_upload: data.file_upload[0],
            }))
            .post('/dev/form-action');
    };

    const openToast = (type = null) => {
        showToast({ type, message: `${type ? type : 'Default'} Toast` });
    };

    const serverToast = (type = null) => {
        router.post('/dev/toast', { type });
    };

    $inspect($form.data());
</script>

<div class="space-y-10">
    <section>
        <h1 class="text-lg font-semibold">Toasts (svelte-sonner)</h1>
        <h3 class="font-medium">Client Toast</h3>
        <div class="mt-2 mb-4 grid gap-5 md:grid-cols-5">
            <Button color="light" onclick={() => openToast()} variant="solid"
                >Open Toast Default</Button>
            <Button color="success" onclick={() => openToast('success')} variant="solid"
                >Open Toast Success</Button>
            <Button color="info" onclick={() => openToast('info')} variant="solid"
                >Open Toast Info</Button>
            <Button color="warning" onclick={() => openToast('warning')} variant="solid"
                >Open Toast warning</Button>
            <Button color="error" onclick={() => openToast('error')} variant="solid"
                >Open Toast Error</Button>
        </div>
        <h3 class="font-medium">Server Toast</h3>
        <div class="mt-2 mb-4 grid gap-5 md:grid-cols-5">
            <Button color="light" onclick={() => serverToast()} variant="solid"
                >Open Toast Default</Button>
            <Button color="success" onclick={() => serverToast('success')} variant="solid"
                >Open Toast Success</Button>
            <Button color="info" onclick={() => serverToast('info')} variant="solid"
                >Open Toast Info</Button>
            <Button color="warning" onclick={() => serverToast('warning')} variant="solid"
                >Open Toast warning</Button>
            <Button color="error" onclick={() => serverToast('error')} variant="solid"
                >Open Toast Error</Button>
        </div>
    </section>
    <section class="grid grid-cols-2 gap-5">
        <Card class="grid grid-cols-2 gap-5" title="Text Inputs">
            <Field title="Text Input (email)">
                <Input name="email" type="email" bind:value={$form.email} />
            </Field>
            <Field title="Password Input">
                <PasswordInput name="password" bind:value={$form.password} />
            </Field>
            <Field title="Phone Input">
                <PhoneInput
                    name="phone"
                    bind:phone={$form.phone}
                    bind:phoneCode={$form.phone_code_input} />
            </Field>
            <Field title="Masked Input">
                <MaskedInput
                    mask={{ mask: '000-000-000[-00000]' }}
                    bind:value={$form.masked_input}
                    bind:maskedValue={$form.masked_value_input} />
                {$form.masked_input} <br />
                {$form.masked_value_input}
            </Field>
            <Field title="Textarea Input">
                <Textarea name="textarea" bind:value={$form.textarea_input}></Textarea>
            </Field>
        </Card>
        <Card class="grid grid-cols-2 gap-5" title="Predefined Inputs">
            <Field title="Radio Input" value={$form.radio_input}>
                <RadioGroup name="radio_input" class="flex" bind:value={$form.radio_input}>
                    <RadioItem name="radio" value="A">Option A</RadioItem>
                    <RadioItem name="radio" value="B">Option B</RadioItem>
                </RadioGroup>
                {$form.radio_input}
            </Field>
            <Field title="Switch Input">
                <Switch name="switch" value="A" bind:checked={$form.switch_input}>Switch A</Switch>
            </Field>
            <Field title="Checkbox Group Input">
                <CheckboxGroup name="checkbox_group_input" bind:value={$form.checkbox_group_input}>
                    <Checkbox name="checkbox" value="A">Checkbox A</Checkbox>
                    <Checkbox name="checkbox" value="B">Checkbox B</Checkbox>
                </CheckboxGroup>
                {JSON.stringify($form.checkbox_group_input)}
            </Field>
            <Field title="Checkbox Input">
                <Checkbox name="checkbox" value="A" bind:checked={$form.checkbox_input}>
                    Checkbox A</Checkbox>
            </Field>
        </Card>
        <Card class="grid grid-cols-2 gap-5" title="Selects">
            <Field title="Basic Select / Auto Complete">
                <Select
                    name="select_input"
                    items={['Items 1', 'Items 2', 'Items 3', 'Items 4', 'Items 5']}
                    bind:value={$form.select_basic_input} />
            </Field>
            <Field title="Select / Auto Complete">
                <Select
                    name="select_input"
                    items={[
                        { label: 'Items 1', value: 1 },
                        { label: 'Items 2', value: 2 },
                        { label: 'Items 3', value: 3 },
                    ]}
                    bind:value={$form.select_input} />
                Select: {$form.select_input}
            </Field>
            <Field title="Advanced Select / Auto Complete">
                <Select
                    name="select_input"
                    items={[
                        {
                            label: 'Items 1',
                            value: 1,
                            description: 'Items 1 is lorem lorem lorem lorem lorem',
                        },
                        {
                            label: 'Items 2',
                            value: 2,
                            description: 'Items 2 is lorem lorem lorem lorem lorem',
                        },
                        {
                            label: 'Items 3',
                            value: 3,
                            description: 'Items 3 is lorem lorem lorem lorem lorem',
                        },
                    ]}
                    bind:value={$form.select_advance_input} />
            </Field>
        </Card>
        <Card class="grid grid-cols-2 gap-5" title="Date Inputs">
            <Field title="Date Picker w Validations">
                <DateInput
                    name="date_input"
                    options={{
                        minDate: new SvelteDate().setDate(new Date().getDate() - 20),
                        maxDate: 'today',
                    }}
                    bind:value={$form.date_input} />
                {$form.date_input}
            </Field>
            <Field title="Date Picker">
                <DateInput name="date_input" bind:value={$form.date_input} />
                {$form.date_input}
            </Field>
            <Field title="Date Time Picker">
                <DateInput
                    name="date_time_input"
                    options={{
                        enableTime: true,
                        altFormat: 'd F Y H:i',
                        dateFormat: 'Y-m-d H:i:S',
                    }}
                    bind:value={$form.date_time_input} />
                {$form.date_time_input}
            </Field>
            <Field title="Month Picker">
                <DateInput
                    name="month_input"
                    options={{ isMonthPicker: true, altFormat: 'F Y', dateFormat: 'Y-m' }}
                    bind:value={$form.month_input} />
                {$form.month_input}
            </Field>
        </Card>
        <Card title="File Uploads">
            <Field title="File Input">
                <FileInput name="file_upload" bind:files={$form.file_upload} />
            </Field>
        </Card>
        <Card title="Masking Inputs">
            <Field
                formMode="data"
                maskValue
                title="Value Field Masked (default masking, allow - _ . ) "
                value="lorem-ipsum dolor.si amet" />
            <Field
                formMode="data"
                maskDataOptions={{ preset: 'email' }}
                maskValue
                title="Value Field Masked (email masking)"
                value="lorem-ipsum123@test.com" />
            <Field
                formMode="data"
                maskDataOptions={{ preset: 'phone' }}
                maskValue
                title="Value Field Masked (phone masking)"
                value="628123123123" />
            <Field
                formMode="data"
                maskDataOptions={{ preset: 'mask-all' }}
                maskValue
                title="Value Field Masked (mask-all masking)"
                value="Jalan panjang penuh kenangan" />
        </Card>
        <Card title="Submit Buttons">
            <div class="flex flex-col gap-6">
                <SubmitButton class="w-full" form="test-form" submitting>
                    Form Submitting</SubmitButton>
                <SubmitButton class="w-full" submitting={false}
                    >Form Submission w Validation</SubmitButton>
            </div>
        </Card>
    </section>
</div>
