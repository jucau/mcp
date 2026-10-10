import type { BookSearch } from './book-search';

export const SHELVES = ['all', 'reading', 'to-read', 'read', 'abandoned', 'wishlist'] as const;

export type Shelf = (typeof SHELVES)[number];

const criteria: Record<Shelf, BookSearch> = {
    all: {},
    reading: { readingStatus: 'reading' },
    'to-read': { readingStatus: 'to_read', owned: true },
    read: { readingStatus: 'read' },
    abandoned: { readingStatus: 'abandoned' },
    wishlist: { owned: false },
};

export const criteriaOf = (shelf: Shelf): BookSearch => criteria[shelf];
