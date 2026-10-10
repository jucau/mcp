import type { Book } from '../model/book';
import type { BookSearch } from '../model/book-search';
import type { Page } from '../model/page';

export interface Library {
    search(criteria: BookSearch): Promise<Page<Book>>;
}
