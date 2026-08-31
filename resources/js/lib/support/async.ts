export function promise<T = void>(): Promise<T> {
    return new Promise<T>((resolve) => {
        resolve(undefined as unknown as T);
    });
}
