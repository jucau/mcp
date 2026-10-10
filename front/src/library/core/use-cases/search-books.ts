import type { Book } from '../model/book';
import type { Page } from '../model/page';
import { criteriaOf, type Shelf } from '../model/shelf';
import type { Library } from '../ports/library';

export interface Browsing {
    readonly shelf: Shelf;
    readonly text: string;
    readonly page: number;
}

export const searchBooks =
    (library: Library) =>
    ({ shelf, text, page }: Browsing): Promise<Page<Book>> =>
        library.search({ ...criteriaOf(shelf), text, page });
