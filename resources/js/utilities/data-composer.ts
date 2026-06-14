import type { FormGeneratorProps } from './form-helper.svelte';
import type { ColumnDef } from '@tanstack/table-core';
import type { AnyRecord, DotNotationKey, ValueOrFunction } from '@type/index';

import { DataTable } from './datatable.svelte';

type TableProps<T> = {
    headerSortable?: boolean;
} & Partial<Omit<ColumnDef<T, any>, 'accessorKey'>>;

export type DataSchemaItem<
    T extends AnyRecord = AnyRecord,
    K extends DotNotationKey<T> = DotNotationKey<T>,
    Meta extends AnyRecord = AnyRecord,
> = {
    class?: string;
    label?: string;
    value?: ValueOrFunction<T, K>;

    meta?: Meta;
    form?: (data?: T) => FormGeneratorProps;
    table?: boolean | TableProps<T>;
    tableFilter?: boolean | ((data?: T) => FormGeneratorProps);
    show?: boolean | ((data: T) => boolean);
};

export type DataSchema<
    T extends AnyRecord = AnyRecord,
    K extends DotNotationKey<T> = DotNotationKey<T>, // K extends keyof T = keyof T,
> = Partial<Record<K, DataSchemaItem<T, K>>>;

export type OptionsOverride<T extends AnyRecord = AnyRecord> = {
    only?: DotNotationKey<T>[];
    except?: DotNotationKey<T>[];
    order?: DotNotationKey<T>[];
    reset?: boolean;
};

export type DataDisplay = {
    key: string;
    label: string;
    value: any;
    class?: string;
    type?: string;
    show?: boolean | ((data: any) => boolean);
};

type DataGetValue =
    | (DataDisplay & { displayValue: any; formField: FormGeneratorProps })
    | undefined;

type FormGeneratorFields<T extends AnyRecord> = Partial<
    Record<DotNotationKey<T>, FormGeneratorProps>
>;

type FormGeneratorData<T extends AnyRecord> = Partial<Record<DotNotationKey<T>, any>>;

type DataTableColumns<T extends AnyRecord, V = any> = ColumnDef<T, V>[];

/**
 * DataComposer is a utility class for transforming raw objects into structured display data.
 * It provides a fluent API for filtering, ordering, and extending schemas.
 *
 * @example
 * // 1. Default use
 * const studentData = new DataComposer(studentSchema, student)
 *     .except(['id', 'user_id'])
 *     .toDataDisplay();
 *
 * // 2. Inheriting schema from data
 * const classroom = DataComposer.fromData(classroom)
 *     .only(['name'])
 *     .toDataDisplay();
 *
 * // 3. Single field retrieval
 * const phone = DataComposer.from(studentSchema, student).get('phone_number');
 */
export class DataComposer<T extends AnyRecord> {
    private schema: DataSchema<T>;
    private data?: T;
    private filterMode: 'only' | 'except' | 'none' = 'none';
    private filterKeys: DotNotationKey<T>[] = [];
    private orderKeys: DotNotationKey<T>[] = [];

    constructor(schema: DataSchema<T>, data?: T) {
        this.schema = DataComposer.cloneSchema(schema);
        this.data = data;
    }

    /**
     * Creates a DataComposer with an explicit schema.
     *
     * @param schema - Field configurations (labels, formatters, etc.)
     * @param data - Optional source data
     * @returns New DataComposer instance
     *
     * @example
     * DataComposer.from(studentSchema, student)
     *     .except(['internal_notes'])
     *     .toDataDisplay();
     */
    static from<T extends AnyRecord>(schema: DataSchema<T>, data?: T) {
        return new DataComposer(schema, data);
    }

    /**
     * Creates a DataComposer with auto-generated schema from data.
     * Labels are generated from keys (snake_case → human-readable).
     *
     * @param data - Source data object
     * @returns New DataComposer instance with generated schema
     *
     * @example
     * DataComposer.fromData({ user_ip: '127.0.0.1', status: 'active' })
     *     .toDataDisplay();
     */
    static fromData<T extends AnyRecord>(data: T) {
        const generatedSchema: DataSchema<T> = {};

        Object.keys(data).forEach((key) => {
            generatedSchema[key as DotNotationKey<T>] = {
                label: DataComposer.labelResolver(key),
                value: data[key],
            };
        });

        return new DataComposer<T>(generatedSchema, data);
    }

    /**
     * Filters and orders a schema without creating an instance.
     *
     * @param schema - Source schema to filter
     * @param options - Filter and ordering options
     * @returns Filtered and ordered schema
     *
     * @example
     * const publicSchema = DataComposer.toSchema(userSchema, {
     *     except: ['password', 'api_token']
     * });
     */
    static toSchema<T extends AnyRecord>(
        schema: DataSchema<T>,
        options?: OptionsOverride<T>
    ): DataSchema<T> {
        // If no options or reset is true, return the entire schema
        if (!options || options?.reset) {
            return { ...schema };
        }

        const schemaKeys = Object.keys(schema) as DotNotationKey<T>[];
        let filteredKeys: DotNotationKey<T>[];

        // Apply filtering logic
        if (options?.only) {
            // Only include specified keys that exist in the schema
            filteredKeys = options.only.filter((key) => schemaKeys.includes(key));
        } else if (options?.except) {
            // Include all keys except the specified ones
            filteredKeys = schemaKeys.filter((key) => !options.except.includes(key));
        } else {
            // No filter specified, return all
            filteredKeys = schemaKeys;
        }

        // Apply ordering if specified
        const orderedKeys = options?.order
            ? [
                  ...options.order.filter((key) => filteredKeys.includes(key)),
                  ...filteredKeys.filter((key) => !options.order.includes(key)),
              ]
            : filteredKeys;

        // Build the filtered and ordered schema
        const finalSchema: DataSchema<T> = {};
        orderedKeys.forEach((key) => {
            finalSchema[key] = schema[key];
        });

        return finalSchema;
    }

    /**
     * Merges multiple schemas into one. Later schemas override earlier ones.
     *
     * @param schemas - Schemas to merge (later takes precedence)
     * @returns Merged schema
     *
     * @example
     * const merged = DataComposer.mergeSchema(
     *     baseSchema,
     *     extensionSchema,
     *     overrideSchema
     * );
     */
    static mergeSchema<T extends AnyRecord>(...schemas: DataSchema<T>[]): DataSchema<T> {
        return schemas.reduce((acc, schema) => ({ ...acc, ...schema }), {} as DataSchema<T>);
    }

    /**
     * Sets or updates the data object.
     *
     * @param data - New data object
     * @returns This instance for chaining
     */
    setData(data: T): this {
        this.data = data;

        return this;
    }

    /**
     * Replaces matching schema items wholesale. Items not present in the
     * argument are preserved; items that are present are replaced entirely
     * (form, table, and other inner properties are dropped unless
     * re-specified).
     *
     * Use {@link extendSchema} to merge inner properties instead.
     *
     * @param schema - Schema items to replace
     * @returns This instance for chaining
     *
     * @example
     * // Original: { name: { label: 'Name', form: () => ({...}) }, email: {...} }
     * composer.overrideSchema({ name: { label: 'Full Name' } });
     * // Result:
     * //   name:  { label: 'Full Name' }   // form is dropped
     * //   email: {...}                    // untouched
     */
    overrideSchema(schema: DataSchema<T>): this {
        this.schema = { ...this.schema, ...schema };

        return this;
    }

    /**
     * Merges schema items, preserving existing properties (shallow merge).
     * For matching keys, top-level fields from the extension override the
     * existing item; missing fields are kept.
     *
     * `form` is special-cased: when both items define a `form` factory, the
     * resulting factory calls both and shallow-merges the produced
     * `FormGeneratorProps` (extension wins on conflicts). Other inner fields
     * (`table`, `tableFilter`, `meta`, ...) are replaced wholesale.
     *
     * @param extension - Schema properties to merge
     * @returns This instance for chaining
     *
     * @example
     * // Original: { name: { label: 'Name', form: () => ({ type: 'text' }) } }
     * composer.extendSchema({ name: { class: 'font-bold' } });
     * // Result: { name: { label: 'Name', form: () => ({ type: 'text' }), class: 'font-bold' } }
     */
    extendSchema(extension: DataSchema<T>): this {
        Object.entries<DataSchemaItem<T>>(extension).forEach(
            ([key, extendedItem]: [DotNotationKey<T>, DataSchemaItem<T>]) => {
                const existingItem = this.schema[key];

                if (existingItem && extendedItem) {
                    this.schema[key] = {
                        ...existingItem,
                        ...extendedItem,

                        // Deep merge nested objects
                        //
                        // Shallow merge; guarded so we don't create `meta: {}`
                        // when neither side defines meta.
                        // meta:
                        //     existingItem.meta || extendedItem.meta
                        //         ? { ...existingItem.meta, ...extendedItem.meta }
                        //         : undefined,

                        form:
                            existingItem.form && extendedItem.form
                                ? (data?: T) => ({
                                      ...existingItem.form!(data),
                                      ...extendedItem.form!(data),
                                  })
                                : (extendedItem.form ?? existingItem.form),

                        // Object/object: shallow merge (extension wins).
                        // Otherwise extension wins, except `true` preserves an
                        // existing object config rather than dropping it.
                        // table:
                        //     typeof existingItem.table === 'object' &&
                        //     typeof extendedItem.table === 'object'
                        //         ? { ...existingItem.table, ...extendedItem.table }
                        //         : extendedItem.table === true &&
                        //             typeof existingItem.table === 'object'
                        //           ? existingItem.table
                        //           : (extendedItem.table ?? existingItem.table),

                        // Mirror form's compositional merge for two factories;
                        // otherwise extension wins (covers boolean cases).
                        // tableFilter:
                        //     typeof existingItem.tableFilter === 'function' &&
                        //     typeof extendedItem.tableFilter === 'function'
                        //         ? (data?: T) => ({
                        //               ...(existingItem.tableFilter as (d?: T) => FormGeneratorProps)(data),
                        //               ...(extendedItem.tableFilter as (d?: T) => FormGeneratorProps)(data),
                        //           })
                        //         : (extendedItem.tableFilter ?? existingItem.tableFilter),
                    };
                } else if (extendedItem) {
                    this.schema[key] = { ...extendedItem };
                }
            }
        );

        return this;
    }

    /**
     * Creates a copy of this instance with the same configuration.
     *
     * @returns New DataComposer instance
     *
     * @example
     * const base = DataComposer.from(schema).except(['password']);
     * const variant = base.clone().only(['name', 'email']);
     */
    clone(): DataComposer<T> {
        const cloned = new DataComposer(this.schema, this.data);
        cloned.filterMode = this.filterMode;
        cloned.filterKeys = [...this.filterKeys];
        cloned.orderKeys = [...this.orderKeys];

        return cloned;
    }

    /**
     * Gets the final schema after applying filters and ordering.
     *
     * @param optionsOverride - Override filters/ordering for this call
     * @returns Processed schema
     *
     * @example
     * const schema = composer.only(['name', 'email']).getSchema();
     */
    getSchema(optionsOverride?: OptionsOverride<T>): DataSchema<T> {
        return this.getFinalSchema(optionsOverride);
    }

    /**
     * Sets field display order (specified fields appear first).
     *
     * @param keys - Field keys in desired order
     * @returns This instance for chaining
     */
    order(keys: DotNotationKey<T>[]): this {
        this.orderKeys = keys;

        return this;
    }

    /**
     * Whitelist fields (mutually exclusive with except).
     *
     * @param keys - Fields to include
     * @returns This instance for chaining
     * @throws Error if except() was already called
     */
    only(keys: DotNotationKey<T>[]): this {
        if (this.filterMode === 'except') {
            throw new Error('DataComposer: Cannot use "only" and "except" together.');
        }

        this.filterMode = 'only';
        this.filterKeys = keys;

        return this;
    }

    /**
     * Blacklist fields (mutually exclusive with only).
     *
     * @param keys - Fields to exclude
     * @returns This instance for chaining
     * @throws Error if only() was already called
     */
    except(keys: DotNotationKey<T>[]): this {
        if (this.filterMode === 'only') {
            throw new Error('DataComposer: Cannot use "except" and "only" together.');
        }

        this.filterMode = 'except';
        this.filterKeys = keys;

        return this;
    }

    /**
     * Retrieves a single field's display configuration.
     *
     * @param key - Field key (supports dot notation)
     * @param sourceData - Override data source
     * @returns Field display object or undefined
     */
    get(key: DotNotationKey<T>, sourceData?: T): DataGetValue {
        const data = sourceData || this.data;
        const column = this.schema[key];

        if (!data || !column) return undefined;

        return {
            key,
            label: DataComposer.labelResolver(key, column),
            value: data[key],
            displayValue: DataComposer.valueResolver(key, column, data),
            class: column?.class,
            formField: this.getFormField(key, column, data),
        };
    }

    /**
     * Retrieves all fields as display objects.
     *
     * @param sourceData - Override data source
     * @param optionsOverride - Override filters (order not applicable)
     * @returns Object mapping keys to DataGetValue objects
     */
    getAll(
        sourceData?: T,
        optionsOverride?: Omit<OptionsOverride<T>, 'order'>
    ): Partial<Record<DotNotationKey<T>, DataGetValue>> {
        const data = sourceData || this.data;

        if (!data) return {};

        const schema = this.getFinalSchema(optionsOverride);
        const result: Partial<Record<DotNotationKey<T>, DataGetValue>> = {};

        Object.keys(schema).forEach((key) => {
            result[key] = this.get(key as DotNotationKey<T>, data);
        });

        return result;
    }

    /**
     * Generates form field configurations from schema. Only fields with a
     * `form` factory are included.
     *
     * @param data - Entity context passed to each `form` factory
     * @param options - Optional fields to prepend or append
     * @returns Object mapping keys to FormGeneratorProps
     *
     * @example
     * const fields = DataComposer.from(schema)
     *     .except(['id'])
     *     .toFormFields(student, {
     *         append: { confirm_password: { ... } }
     *     });
     */
    toFormFields(
        data?: T,
        options?: {
            prepend?: Record<string, FormGeneratorProps>;
            append?: Record<string, FormGeneratorProps>;
        }
    ) {
        const schema = this.getFormSchema();
        const fields: Record<string, FormGeneratorProps> = {
            ...(options?.prepend ?? {}),
        };

        Object.keys(schema).forEach((key) => {
            const column: DataSchemaItem<T> = schema[key];

            fields[key] = this.getFormField(key, column, data);
        });

        return {
            ...fields,
            ...(options?.append ?? {}),
        };
    }

    /**
     * Generates initial form data with default values.
     *
     * @param defaultValues - Default field values
     * @returns Form data object
     *
     * @example
     * const data = composer.toFormData({ name: 'John', status: 'active' });
     */
    toFormData(defaultValues?: FormGeneratorData<T>) {
        const schema = this.getFormSchema();
        const data: FormGeneratorData<T> = {};

        Object.keys(schema).forEach((key) => {
            const form = DataComposer.resolveForm(schema[key] as never, defaultValues);

            data[key] = defaultValues?.[key] ?? form?.default ?? null;
        });

        return data;
    }

    /**
     * Bundles {@link toFormFields} and {@link toFormData} into a single result.
     *
     * `defaultValues` is used as initial form data. The same value (or
     * `this.data` as fallback) is passed as entity context to each `form`
     * factory in the field configs.
     *
     * @param defaultValues - Initial form-field values
     * @param options - Optional fields to prepend or append
     * @returns `{ fields, data }` — field configs and seeded form data
     *
     * @example
     * const { fields, data } = DataComposer.from(studentSchema)
     *     .except(['id'])
     *     .toFormGenerator({ name: 'Jane Doe' }, { append: { ... } });
     */
    toFormGenerator(
        defaultValues?: FormGeneratorData<T>,
        options?: {
            prepend?: Record<string, FormGeneratorProps>;
            append?: Record<string, FormGeneratorProps>;
        }
    ) {
        const formData = this.toFormData(defaultValues);
        const dataContext = (defaultValues as T | undefined) ?? this.data;
        const fields = this.toFormFields(dataContext, options);

        return {
            fields,
            data: formData,
        };
    }

    /**
     * Transforms data into an array of display objects.
     *
     * @param sourceData - Override data source
     * @param optionsOverride - Override filters and ordering
     * @returns Array of DataDisplay objects
     */
    toDataDisplay(sourceData?: T, optionsOverride?: OptionsOverride<T>): DataDisplay[] {
        const data = sourceData || this.data;

        if (!data) return [];

        const schema = this.getFinalSchema(optionsOverride);

        return Object.keys(schema)
            .filter((key) => {
                const column = schema[key];

                if (typeof column.show === 'function') {
                    return column.show(data);
                }

                return column.show ?? true;
            })
            .map((key) => {
                const column = schema[key];

                return {
                    key,
                    label: DataComposer.labelResolver(key, column),
                    value: DataComposer.valueResolver(key, column, data),
                    class: column?.class,
                    show: column.show,
                };
            });
    }

    /**
     * Generates datatable filter configurations from schema. A schema field
     * is included when its `tableFilter` is a factory function, or `true` and
     * the field has a `form` factory to fall back to.
     *
     * @param optionsOverride - Override filters and ordering
     * @returns Array of `{ name, label, form }` filter entries
     *
     * @example
     * const filters = composer.toDatatableFilters();
     */
    toDatatableFilters(optionsOverride?: OptionsOverride<T>) {
        const schema = this.getFinalSchema(optionsOverride);
        const filters: FormGeneratorFields<T> = {};

        Object.entries<DataSchemaItem<T>>(schema).forEach(([key, column]) => {
            // Skip if tableFilter is not defined
            if (!column?.tableFilter) return;

            // If tableFilter is true, fall back to the form property
            if (column.tableFilter === true && Boolean(column?.form)) {
                filters[key] = this.getFormField(key, column);
            }

            if (typeof column.tableFilter === 'function') {
                // Call the tableFilter function to get FormGeneratorProps
                filters[key] = {
                    name: key,
                    title: DataComposer.labelResolver(key, column),
                    ...column.tableFilter(),
                } satisfies FormGeneratorProps;

                return;
            }
        });

        const newFilters = Object.entries(filters).map(([key, filter]) => {
            const column = schema[key];

            return {
                name: key,
                label: column.label,
                form: filter,
            };
        });

        return newFilters;
    }

    /**
     * Generates TanStack Table column definitions from schema.
     *
     * @param options - Columns to prepend/append
     * @param optionsOverride - Override filters and ordering
     * @returns Array of ColumnDef objects
     *
     * @example
     * const columns = composer.toDatatableColumn(
     *     { append: [actionColumn] }
     * );
     */
    toDatatableColumn(
        options?: {
            prepend?: DataTableColumns<T>;
            append?: DataTableColumns<T>;
        },
        optionsOverride?: OptionsOverride<T>
    ): DataTableColumns<T> {
        const schema = this.getFinalSchema(optionsOverride);

        const columns = Object.entries(schema).map(([key, column]: [string, DataSchemaItem<T>]) => {
            if (!column?.table) return;

            const { headerSortable, ...tableProps } =
                typeof column.table === 'object' ? column.table : { headerSortable: true };

            const label = DataComposer.labelResolver(key, column);

            const header =
                tableProps?.header ??
                (!headerSortable
                    ? label
                    : ({ column }) => DataTable.sortableHeader({ column, title: label }));

            const cell =
                tableProps?.cell ??
                (({ row }) => DataComposer.valueResolver(key, column, row.original));

            const table = {
                accessorKey: key,
                ...tableProps,
                header,
                cell,
            };

            return table;
        });

        return [...(options?.prepend ?? []), ...columns, ...(options?.append ?? [])].filter(
            Boolean
        );
    }

    /**
     * Creates a DataTable instance with schema-based columns. Falls back to
     * `this.data` when `sourceData` is omitted (some callers seed the table
     * data via the constructor's `data` slot).
     *
     * @param sourceData - Table data array
     * @param columnOptions - Columns to prepend/append
     * @param options - DataTable configuration
     * @param optionsOverride - Override filters and ordering
     * @returns DataTable instance
     *
     * @example
     * const table = composer.toDatatable(students, { append: [actionColumn] });
     */
    toDatatable(
        sourceData?: ConstructorParameters<typeof DataTable<T>>[0],
        columnOptions?: { prepend?: DataTableColumns<T>; append?: DataTableColumns<T> },
        options?: ConstructorParameters<typeof DataTable<T>>[2],
        optionsOverride?: OptionsOverride<T>
    ) {
        const data = sourceData ?? this.data;

        return new DataTable<T>(
            // @ts-expect-error this.data is typed as a single record T, but the
            // composer also accepts an array as data (see formulation-detail).
            data,
            this.toDatatableColumn(columnOptions, optionsOverride),
            options
        );
    }

    /**
     * Filters a list of keys based on the current filterMode ('only' or 'except').
     *
     * @param keys - The array of keys to be filtered
     * @param optionsOverride - Optional options override for this call only
     * @returns A subset of keys that passed the filter criteria
     */
    protected getFilteredKeys(
        keys: DotNotationKey<T>[],
        optionsOverride?: OptionsOverride<T>
    ): DotNotationKey<T>[] {
        // Reset bypasses all filters
        if (optionsOverride?.reset) {
            return keys;
        }

        // Use optionsOverride if provided, otherwise use instance filters
        if (optionsOverride?.only) {
            const overrideKeys = optionsOverride.only;

            return overrideKeys.filter((key) => keys.includes(key));
        }

        if (optionsOverride?.except) {
            const overrideKeys = optionsOverride.except;

            return keys.filter((key) => !overrideKeys.includes(key));
        }

        // Default to instance filter mode
        switch (this.filterMode) {
            case 'only':
                return this.filterKeys.filter((key) => keys.includes(key));
            case 'except':
                return keys.filter((key) => !this.filterKeys.includes(key));
            default:
                return keys;
        }
    }

    /**
     * Rearranges the keys based on the orderKeys array or override.
     * Any keys in orderKeys that exist in the input array will be moved to the front.
     *
     * @param keys - The array of keys to be ordered
     * @param optionsOverride - Optional options override for this call only
     * @returns The ordered array of keys
     */
    protected getOrderedKeys(
        keys: DotNotationKey<T>[],
        optionsOverride?: OptionsOverride<T>
    ): string[] {
        // Use override order if provided, otherwise use instance orderKeys
        const orderKeys = optionsOverride?.order || this.orderKeys;

        if (orderKeys.length === 0) return keys;

        const ordered = orderKeys.filter((key) => keys.includes(key));
        const remaining = keys.filter((key) => !orderKeys.includes(key));

        return [...ordered, ...remaining];
    }

    /**
     * Compiles the final DataSchema by applying both filtering and ordering logic.
     * This is the orchestrator for the internal transformation pipeline.
     *
     * @param optionsOverride - Optional options override for this call only
     * @returns A new DataSchema object representing the final structured state
     */
    protected getFinalSchema(optionsOverride?: OptionsOverride<T>) {
        const keys = Object.keys(this.schema) as DotNotationKey<T>[];

        const filteredKeys = this.getFilteredKeys(keys, optionsOverride);
        const orderedKeys = this.getOrderedKeys(filteredKeys, optionsOverride);

        const finalSchema: DataSchema<T> = {};

        orderedKeys.forEach((key) => {
            finalSchema[key] = this.schema[key];
        });

        return finalSchema;
    }

    /**
     * Filters the final schema to only include fields with form configurations.
     * This is an internal helper method used by form-related public methods.
     *
     * @returns A DataSchema containing only fields that have a `form` property defined
     */
    protected getFormSchema() {
        return Object.fromEntries(
            Object.entries<DataSchemaItem<T>>(this.getFinalSchema()).filter(([key, column]) =>
                Boolean(column?.form)
            )
        );
    }

    /**
     * Builds a single field's FormGeneratorProps from a schema item.
     * Resolves the form factory with `data` and folds the column-level `show`
     * in as a fallback when the factory itself doesn't set one.
     */
    protected getFormField(key: string, column: DataSchemaItem<T>, data?: T) {
        const resolvedForm = DataComposer.resolveForm(column, data);
        const form = { ...resolvedForm, show: resolvedForm?.show ?? column?.show };

        return {
            name: key,
            title: DataComposer.labelResolver(key, column),
            ...form,
        } satisfies FormGeneratorProps;
    }

    /**
     * Invokes the `form` factory on a schema item.
     *
     * @param column - The schema item containing the form factory
     * @param data - Entity context passed to the factory
     * @returns The resolved FormGeneratorProps, or undefined if no form factory is set
     */
    private static resolveForm<T extends AnyRecord>(
        column: DataSchemaItem<T>,
        data?: T
    ): FormGeneratorProps | undefined {
        if (!column?.form) return undefined;

        return column.form(data);
    }

    /**
     * Returns a copy of the schema where every item is also copied.
     * Prevents mutation of an upstream module-level schema constant when an
     * instance later mutates its own schema (extendSchema, overrideSchema).
     */
    private static cloneSchema<T extends AnyRecord>(schema: DataSchema<T>): DataSchema<T> {
        const copy: DataSchema<T> = {};

        Object.keys(schema).forEach((key) => {
            const item = schema[key as DotNotationKey<T>];
            copy[key as DotNotationKey<T>] = item ? { ...item } : item;
        });

        return copy;
    }

    /**
     * Resolves a display label for a given key.
     * Fallback logic: "first_name" -> "first name".
     *
     * @param key - The field key
     * @param column - Optional schema configuration for the field
     */
    private static labelResolver<T extends AnyRecord>(
        key: string,
        column?: DataSchemaItem<T>
    ): string {
        return column?.label ?? key.replaceAll(/\./g, ' ').replaceAll(/_/g, ' ');
    }

    /**
     * Resolves the display value for a field, handling dot notation and custom formatters.
     * Fallback logic: Resolves nested paths or returns "-" if data is missing.
     *
     * @param key - The field key (supports dot notation)
     * @param column - Schema configuration containing potential value formatters
     * @param data - The source object to extract from
     */
    private static valueResolver<T extends AnyRecord>(
        key: string,
        column: DataSchemaItem<T>,
        data: T
    ) {
        if (typeof column?.value === 'function') return column.value(data);

        // Use != instead of !== to handle null and undefined values
        if (column?.value != undefined) return column.value;

        if (key.includes('.'))
            return key.split('.').reduce((obj: any, i: string) => obj?.[i], data) ?? '-';

        return data[key] ?? '-';
    }
}
