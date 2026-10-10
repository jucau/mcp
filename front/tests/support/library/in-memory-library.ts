import type { Book } from '../../../src/library/core/model/book';
import type { BookSearch } from '../../../src/library/core/model/book-search';
import type { Page } from '../../../src/library/core/model/page';
import type { Library } from '../../../src/library/core/ports/library';

export const PER_PAGE = 20;

export class InMemoryLibrary implements Library {
    private readonly books: Book[];

    constructor(...books: Book[]) {
        this.books = books;
    }

    search(criteria: BookSearch): Promise<Page<Book>> {
        const page = criteria.page ?? 1;
        const perPage = criteria.perPage ?? PER_PAGE;
        const found = this.books
            .filter((book) => criteria.text === undefined || this.mentions(book, criteria.text))
            .filter((book) => criteria.readingStatus === undefined || book.readingStatus === criteria.readingStatus)
            .filter((book) => criteria.owned === undefined || book.owned === criteria.owned)
            .filter((book) => criteria.lent === undefined || (book.lentTo !== null) === criteria.lent)
            .sort((one, other) => one.title.localeCompare(other.title));

        return Promise.resolve({
            items: found.slice((page - 1) * perPage, page * perPage),
            page,
            perPage,
            total: found.length,
        });
    }

    private mentions(book: Book, text: string): boolean {
        const haystack = `${book.title} ${book.author}`.toLowerCase();

        return text
            .toLowerCase()
            .split(/\s+/)
            .every((word) => haystack.includes(word));
    }
}
