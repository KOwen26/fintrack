/**
 * 1. STATIC FIELDS (readonly constants)
 * 2. INSTANCE FIELDS (private, readonly first)
 * 3. GETTERS / SETTERS
 * 4. CONSTRUCTOR
 * 5. STATIC FACTORY
 * 6. STATIC METHODS
 * 7. PUBLIC INSTANCE METHODS
 * 8. PROTECTED METHODS
 * 9. PRIVATE METHODS
 * 10. TO‑JSON / SERIALISATION
 */
export class CounterHelper {
    // ──────────────────────────────────────
    // 1. STATIC FIELDS (readonly constants)
    // ──────────────────────────────────────
    private static readonly DEFAULT_START = 0;
    private static readonly MAX_SAFE_VALUE = 999_999;

    // ──────────────────────────────────────
    // 2. INSTANCE FIELDS (private, readonly first)
    // ──────────────────────────────────────
    private readonly _start: number;
    private _current: number;
    private _isPaused: boolean;

    // ──────────────────────────────────────
    // 3. GETTERS / SETTERS
    // ──────────────────────────────────────
    get current(): number {
        return this._current;
    }
    get isPaused(): boolean {
        return this._isPaused;
    }

    // ──────────────────────────────────────
    // 4. CONSTRUCTOR
    // ──────────────────────────────────────
    private constructor(start: number = CounterHelper.DEFAULT_START) {
        this._start = Math.max(0, start);
        this._current = this._start;
        this._isPaused = false;
    }

    // ──────────────────────────────────────
    // 5. STATIC FACTORY
    // ──────────────────────────────────────
    static create(start: number = 0): CounterHelper {
        if (start > CounterHelper.MAX_SAFE_VALUE) {
            throw new Error(`Start value exceeds ${CounterHelper.MAX_SAFE_VALUE}`);
        }

        return new CounterHelper(start);
    }

    // ──────────────────────────────────────
    // 6. STATIC METHODS (pure utilities)
    // ──────────────────────────────────────
    static format(value: number, prefix: string = '#'): string {
        return `${prefix}${value.toString().padStart(6, '0')}`;
    }

    static isValid(value: number): boolean {
        return Number.isInteger(value) && value >= 0 && value <= CounterHelper.MAX_SAFE_VALUE;
    }

    // ──────────────────────────────────────
    // 7. PUBLIC INSTANCE METHODS
    // ──────────────────────────────────────
    increment(step: number = 1): this {
        if (this._isPaused) return this;
        if (!CounterHelper.isValid(this._current + step)) {
            throw new Error('Counter overflow');
        }
        this._current += step;

        return this;
    }

    decrement(step: number = 1): this {
        if (this._isPaused) return this;
        const next = this._current - step;
        if (next < 0) {
            this._current = 0;
        } else {
            this._current = next;
        }

        return this;
    }

    reset(): this {
        this._current = this._start;
        this._isPaused = false;

        return this;
    }

    pause(): this {
        this._isPaused = true;

        return this;
    }

    resume(): this {
        this._isPaused = false;

        return this;
    }

    // ──────────────────────────────────────
    // 8. PROTECTED METHODS (for subclassing)
    // ──────────────────────────────────────
    protected clamp(value: number): number {
        return Math.max(0, Math.min(value, CounterHelper.MAX_SAFE_VALUE));
    }

    // ──────────────────────────────────────
    // 9. PRIVATE METHODS (helpers)
    // ──────────────────────────────────────
    private validateStep(step: number): void {
        if (!Number.isInteger(step) || step <= 0) {
            throw new Error('Step must be a positive integer');
        }
    }

    // ──────────────────────────────────────
    // 10. TO‑JSON / SERIALISATION
    // ──────────────────────────────────────
    toJSON(): { start: number; current: number; paused: boolean } {
        return {
            start: this._start,
            current: this._current,
            paused: this._isPaused,
        };
    }

    toString(): string {
        return CounterHelper.format(this._current);
    }
}
