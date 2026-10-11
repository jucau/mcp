import type { Book } from '../model/book';
import type { BookSearch } from '../model/book-search';
import type { Page } from '../model/page';
import type { Outcome } from '../model/refusal';

export interface Library {
    search(criteria: BookSearch): Promise<Page<Book>>;
    startReading(bookId: string): Promise<Outcome<Book>>;
    finishReading(bookId: string): Promise<Outcome<Book>>;
    abandonReading(bookId: string): Promise<Outcome<Book>>;
    rate(bookId: string, rating: number): Promise<Outcome<Book>>;
    lend(bookId: string, borrower: string): Promise<Outcome<Book>>;
    returnBook(bookId: string): Promise<Outcome<Book>>;
    acquire(bookId: string): Promise<Outcome<Book>>;
    remove(bookId: string): Promise<Outcome<null>>;
}
