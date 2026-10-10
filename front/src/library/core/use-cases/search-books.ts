import type { Book } from '../model/book';
import type { BookSearch } from '../model/book-search';
import type { Page } from '../model/page';
import type { Library } from '../ports/library';

export const searchBooks =
    (library: Library) =>
    (criteria: BookSearch): Promise<Page<Book>> =>
        library.search(criteria);
