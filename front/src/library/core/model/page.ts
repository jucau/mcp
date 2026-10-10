export interface Page<T> {
    readonly items: readonly T[];
    readonly page: number;
    readonly perPage: number;
    readonly total: number;
}
