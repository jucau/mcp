import type { Book } from '../model/book';
import type { BookAction } from '../model/book-action';
import type { Outcome } from '../model/refusal';
import type { Library } from '../ports/library';

export const actOn =
    (library: Library) =>
    (bookId: string, action: BookAction): Promise<Outcome<Book>> => {
        switch (action.kind) {
            case 'start-reading':
                return library.startReading(bookId);
            case 'finish-reading':
                return library.finishReading(bookId);
            case 'abandon-reading':
                return library.abandonReading(bookId);
            case 'rate':
                return library.rate(bookId, action.rating);
            case 'lend':
                return library.lend(bookId, action.borrower);
            case 'return':
                return library.returnBook(bookId);
            case 'acquire':
                return library.acquire(bookId);
        }
    };
