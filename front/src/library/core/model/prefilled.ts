import type { CatalogEntry } from './catalog-entry';
import type { NewBook } from './new-book';

export const prefilled = (book: NewBook, entry: CatalogEntry): NewBook => ({
    ...book,
    isbn: entry.isbn,
    title: entry.title,
    author: entry.author ?? book.author,
});
