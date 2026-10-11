import type { Book } from '../model/book';
import type { BookSearch } from '../model/book-search';
import type { CatalogEntry } from '../model/catalog-entry';
import type { NewBook } from '../model/new-book';
import type { Page } from '../model/page';
import type { Outcome } from '../model/refusal';

export interface Library {
    search(criteria: BookSearch): Promise<Page<Book>>;
    lookUp(isbn: string): Promise<Outcome<CatalogEntry>>;
    add(book: NewBook): Promise<Outcome<Book>>;
    startReading(bookId: string): Promise<Outcome<Book>>;
    finishReading(bookId: string): Promise<Outcome<Book>>;
    abandonReading(bookId: string): Promise<Outcome<Book>>;
    rate(bookId: string, rating: number): Promise<Outcome<Book>>;
    lend(bookId: string, borrower: string): Promise<Outcome<Book>>;
    returnBook(bookId: string): Promise<Outcome<Book>>;
    acquire(bookId: string): Promise<Outcome<Book>>;
    remove(bookId: string): Promise<Outcome<null>>;
}
