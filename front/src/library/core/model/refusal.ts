export interface Refusal {
    readonly type: string;
    readonly detail: string;
}

export type Outcome<T> = { readonly ok: true; readonly value: T } | { readonly ok: false; readonly refusal: Refusal };
