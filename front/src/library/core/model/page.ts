export interface Page<T> {
    readonly items: readonly T[];
    readonly page: number;
    readonly perPage: number;
    readonly total: number;
}

export const pageCount = (page: Page<unknown>): number => Math.max(1, Math.ceil(page.total / page.perPage));
