/**
 * Minimal IoC toolkit, framework-agnostic. Two primitives:
 *
 * - `Container`: token → factory bindings with lazy resolution and optional
 *   singleton caching. Nothing here is specific to any domain; any subsystem
 *   can bind and resolve its own services.
 * - `Registry`: a keyed plugin registry (last registration wins, optional
 *   fallback) for "everything is a plugin" designs where implementations are
 *   looked up by a discriminator (a block type, a layout name, a format…).
 *
 * Kept dependency-free and extension-imported so Node can run consumers
 * natively via type stripping (scripts/present.mjs).
 */

export type Factory<T> = (container: Container) => T;

export class Container {
    private factories = new Map<string, Factory<unknown>>();
    private singletons = new Set<string>();
    private instances = new Map<string, unknown>();

    /** Bind a factory; a fresh instance is made on every `make()`. */
    bind<T>(token: string, factory: Factory<T>): this {
        this.factories.set(token, factory);
        this.singletons.delete(token);
        this.instances.delete(token);

        return this;
    }

    /** Bind a factory whose first `make()` result is cached and reused. */
    singleton<T>(token: string, factory: Factory<T>): this {
        this.bind(token, factory);
        this.singletons.add(token);

        return this;
    }

    /** Bind an already-built value. */
    instance<T>(token: string, value: T): this {
        this.factories.delete(token);
        this.singletons.delete(token);
        this.instances.set(token, value);

        return this;
    }

    has(token: string): boolean {
        return this.instances.has(token) || this.factories.has(token);
    }

    make<T>(token: string): T {
        if (this.instances.has(token)) {
            return this.instances.get(token) as T;
        }

        const factory = this.factories.get(token);

        if (!factory) {
            throw new Error(`Container: nothing bound for token "${token}".`);
        }

        const value = factory(this) as T;

        if (this.singletons.has(token)) {
            this.instances.set(token, value);
        }

        return value;
    }
}

export class Registry<V> {
    private entries = new Map<string, V>();
    private fallbackEntry: V | null = null;
    private label: string;

    constructor(label: string) {
        this.label = label;
    }

    register(key: string, value: V): this {
        this.entries.set(key, value);

        return this;
    }

    /** Entry returned when no key matches; opt-in, unset registries throw. */
    fallback(value: V): this {
        this.fallbackEntry = value;

        return this;
    }

    get(key: string): V | undefined {
        return this.entries.get(key) ?? this.fallbackEntry ?? undefined;
    }

    resolve(key: string): V {
        const value = this.get(key);

        if (value === undefined) {
            throw new Error(
                `Registry(${this.label}): nothing registered for "${key}" and no fallback set.`,
            );
        }

        return value;
    }
}
