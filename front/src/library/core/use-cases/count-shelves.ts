import { criteriaOf, SHELVES, type Shelf } from '../model/shelf';
import type { Library } from '../ports/library';

export const countShelves = (library: Library) => async (): Promise<Record<Shelf, number>> => {
    const totals = await Promise.all(
        SHELVES.map(
            async (shelf) => [shelf, (await library.search({ ...criteriaOf(shelf), perPage: 1 })).total] as const,
        ),
    );

    return Object.fromEntries(totals) as Record<Shelf, number>;
};
