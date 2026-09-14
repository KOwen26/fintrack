<script lang="ts" module>
    export type CalculatorOperator = '+' | '-' | '×' | '÷';

    export type CalculatorInputProps = {
        /** Bindable — the number being built, also the result after '=' */
        value: number;
        /** Bindable — operator currently in flight, '' when none (render it in your own display) */
        currentOperator?: CalculatorOperator | '';
        /** Maximum digits per entry. Default 12 */
        maxDigits?: number;
        disabled?: boolean;
        class?: string;
    };
</script>

<script lang="ts">
    import { cn } from '@utilities/shadcn';

    let {
        value = $bindable(0),
        currentOperator = $bindable<CalculatorOperator | ''>(''),
        maxDigits = 12,
        disabled = false,
        class: className,
    }: CalculatorInputProps = $props();

    type KeyType = 'digit' | 'clear' | 'backspace' | 'operator' | 'equals';
    type Key = { label: string; type: KeyType; icon?: string };

    const KEYS: Key[] = [
        { label: 'C', type: 'clear' },
        { label: '⌫', type: 'backspace', icon: 'tabler--backspace' },
        { label: '÷', type: 'operator', icon: 'tabler--divide' },
        { label: '×', type: 'operator', icon: 'tabler--x' },
        { label: '7', type: 'digit' },
        { label: '8', type: 'digit' },
        { label: '9', type: 'digit' },
        { label: '-', type: 'operator', icon: 'tabler--minus' },
        { label: '4', type: 'digit' },
        { label: '5', type: 'digit' },
        { label: '6', type: 'digit' },
        { label: '+', type: 'operator', icon: 'tabler--plus' },
        { label: '1', type: 'digit' },
        { label: '2', type: 'digit' },
        { label: '3', type: 'digit' },
        { label: '=', type: 'equals', icon: 'tabler--equal' },
        { label: '000', type: 'digit' },
        { label: '0', type: 'digit' },
        { label: '00', type: 'digit' },
    ];

    const KEY_CLASS: Record<KeyType, string> = {
        digit: 'bg-base-200 text-base-content hover:bg-base-300',
        clear: 'bg-error/10 text-error hover:bg-error/20',
        backspace: 'bg-error/10 text-error hover:bg-error/20',
        operator: 'bg-primary/10 text-primary hover:bg-primary/20',
        equals: 'bg-primary text-primary-content hover:bg-primary/90',
    };

    /** Physical keyboard keys mapped to operators */
    const OPERATOR_KEYS: Record<string, CalculatorOperator> = {
        '+': '+',
        '-': '-',
        '*': '×',
        '/': '÷',
    };

    // Non-reactive calculator memory — only `value` / `currentOperator` need to be reactive
    let accumulator: number | null = null;
    let isFresh = false;

    /** Formatted value for the screen reader live region */
    const spokenValue = $derived(Number(value) ? Number(value).toLocaleString('id-ID') : '0');

    /** Current value as a plain digit string */
    function toDigits(): string {
        return String(Math.floor(Number(value)) || 0);
    }

    function apply(operand: number, op: CalculatorOperator, entry: number): number {
        switch (op) {
            case '+':
                return operand + entry;
            case '-':
                return operand - entry;
            case '×':
                return operand * entry;
            case '÷':
                return entry === 0 ? 0 : Math.round((operand / entry) * 100) / 100;
        }
    }

    function pressDigit(label: string): void {
        if (isFresh) {
            value = Number(label) || 0;
            isFresh = false;

            return;
        }

        const current = toDigits();
        if (current.length >= maxDigits) return;

        const next = current === '0' ? label : current + label;
        value = Number(next.replace(/^0+(?=\d)/, '').slice(0, maxDigits)) || 0;
    }

    function pressOperator(op: CalculatorOperator): void {
        if (currentOperator && accumulator !== null && !isFresh) {
            value = apply(accumulator, currentOperator, Number(value) || 0);
        }
        accumulator = Number(value) || 0;
        currentOperator = op;
        isFresh = true;
    }

    function pressEquals(): void {
        if (currentOperator && accumulator !== null) {
            value = apply(accumulator, currentOperator, Number(value) || 0);
        }
        accumulator = null;
        currentOperator = '';
        isFresh = true;
    }

    function backspace(): void {
        isFresh = false;
        value = Number(toDigits().slice(0, -1)) || 0;
    }

    function clearAll(): void {
        value = 0;
        accumulator = null;
        currentOperator = '';
        isFresh = false;
    }

    function onclick(key: Key): void {
        if (disabled) return;

        switch (key.type) {
            case 'digit':
                return pressDigit(key.label);
            case 'operator':
                return pressOperator(key.label as CalculatorOperator);
            case 'equals':
                return pressEquals();
            case 'clear':
                return clearAll();
            case 'backspace':
                return backspace();
        }
    }

    /**
     * Physical keyboard support — digits and operators type directly,
     * Enter/Space stay native (they activate the focused key button).
     */
    function onkeydown(event: KeyboardEvent): void {
        if (disabled || event.ctrlKey || event.metaKey || event.altKey) return;

        const { key } = event;

        if (/^[0-9]$/.test(key)) {
            event.preventDefault();
            pressDigit(key);

            return;
        }

        const operatorKey = OPERATOR_KEYS[key];
        if (operatorKey) {
            event.preventDefault();
            pressOperator(operatorKey);

            return;
        }

        switch (key) {
            case 'Backspace':
                event.preventDefault();
                backspace();
                break;
            case 'Escape':
            case 'Delete':
                event.preventDefault();
                clearAll();
                break;
        }
    }
</script>

<!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
<div
    class={cn(
        'grid w-full grid-cols-4 gap-1.5',
        disabled && 'cursor-not-allowed opacity-50',
        className
    )}
    aria-label="Calculator keypad"
    {onkeydown}
    role="group">
    <span class="sr-only" aria-live="polite">{spokenValue}</span>
    {#each KEYS as key (key.label)}
        <button
            class={cn(
                'flex min-h-12 w-full cursor-pointer items-center justify-center rounded-md font-mono text-lg font-semibold transition-[background-color,color,transform,box-shadow] duration-150 select-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-base-100 focus-visible:outline-none active:scale-95 disabled:pointer-events-none disabled:opacity-40',
                KEY_CLASS[key.type],
                key.type === 'equals' && 'row-span-2',
                key.type === 'operator' &&
                    currentOperator === key.label &&
                    'bg-primary text-primary-content shadow-inner'
            )}
            aria-label={key.type === 'backspace' ? 'Hapus satu angka' : undefined}
            {disabled}
            onclick={() => onclick(key)}
            type="button">
            {#if key.icon}
                <i class="iconify size-5 {key.icon}"></i>
            {:else}
                {key.label}
            {/if}
        </button>
    {/each}
</div>
