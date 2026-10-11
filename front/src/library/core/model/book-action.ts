export type BookAction =
    | { readonly kind: 'start-reading' | 'finish-reading' | 'abandon-reading' }
    | { readonly kind: 'rate'; readonly rating: number }
    | { readonly kind: 'lend'; readonly borrower: string }
    | { readonly kind: 'return' | 'acquire' };

export type BookActionKind = BookAction['kind'];
