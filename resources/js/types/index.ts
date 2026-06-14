import type { Snippet, SvelteComponent } from 'svelte';

export type RequiredProperty<Type, Key extends keyof Type> = Type & {
    [Property in Key]-?: Type[Property];
};

export interface RestProps {
    children?: Snippet | Snippet<any[]>;
    class?: string | any;
    [key: string]: any;
}

export type ToastType = 'default' | 'success' | 'info' | 'warning' | 'error';

/**
 * Type that combines all properties of `T` into a new type.
 *
 * @template T - The type whose properties are to be combined.
 */
export type Combine<T> = {
    /**
     * Each property of `T` is included in the resulting type.
     *
     * @remarks
     * This is a mapped type that iterates over each key in `T` and includes that key in the resulting type.
     * The type of the key is preserved in the resulting type.
     */
    [K in keyof T]: T[K];
} & object;

export type DotNotationKey<T> = T extends object
    ? {
          [K in keyof T & string]: T[K] extends Record<string, any>
              ? // eslint-disable-next-line @typescript-eslint/no-unsafe-function-type
                T[K] extends Date | any[] | Function | string // Force stop for these
                  ? K
                  : K | `${K}.${DotNotationKey<T[K]>}`
              : K;
      }[keyof T & string]
    : never;

export type DotNotationValue<T, P extends string> = P extends `${infer Key}.${infer Rest}`
    ? Key extends keyof T
        ? // eslint-disable-next-line @typescript-eslint/no-unsafe-function-type
          T[Key] extends Date | any[] | Function | string | number | boolean
            ? T[Key]
            : DotNotationValue<T[Key], Rest>
        : never
    : P extends keyof T
      ? T[P]
      : never;

export type AnyRecord = Record<string, any>;

// eslint-disable-next-line @typescript-eslint/no-unsafe-function-type
export type ValueOrFunction<T extends AnyRecord, K extends keyof T> = T[K] extends Function
    ? T[K] | ((value: T) => any)
    : T[K] | ((value: T) => any);

export type GetSvelte4SlotsProps<T> = T extends SvelteComponent<any, any, infer S> ? S : never;

export type PickAndWrapOthers<T, K extends keyof T, RestKey extends string = 'rest'> = Pick<
    T,
    K
> & {
    [P in RestKey]?: Partial<Omit<T, K>>;
};

export type WithoutValue<T, ValueKey extends string = 'value'> = Omit<T, ValueKey>;
