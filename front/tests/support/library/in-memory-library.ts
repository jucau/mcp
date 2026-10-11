import type { Book } from '../../../src/library/core/model/book';
import type { BookSearch } from '../../../src/library/core/model/book-search';
import type { Page } from '../../../src/library/core/model/page';
import type { Outcome, Refusal } from '../../../src/library/core/model/refusal';
import type { Library } from '../../../src/library/core/ports/library';

export const PER_PAGE = 20;
export const TODAY = '2026-10-11';

/**
 * Applies the effect of every action, and checks none of its conditions: knowing why the API refuses would mean
 * copying the domain. A test that needs a refusal programs it.
 */
export class InMemoryLibrary implements Library {
    private readonly books: Map<string, Book>;
    private refusal: Refusal | undefined;

    constructor(...books: Book[]) {
        this.books = new Map(books.map((book) => [book.id, book]));
    }

    refuseNext(type: string, detail = `Refused: ${type}.`): void {
        this.refusal = { type, detail };
    }

    book(bookId: string): Book | undefined {
        return this.books.get(bookId);
    }

    search(criteria: BookSearch): Promise<Page<Book>> {
        const page = criteria.page ?? 1;
        const perPage = criteria.perPage ?? PER_PAGE;
        const found = [...this.books.values()]
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

    startReading(bookId: string): Promise<Outcome<Book>> {
        return this.change(bookId, { readingStatus: 'reading', startedOn: TODAY, finishedOn: null, rating: null });
    }

    finishReading(bookId: string): Promise<Outcome<Book>> {
        return this.change(bookId, { readingStatus: 'read', finishedOn: TODAY });
    }

    abandonReading(bookId: string): Promise<Outcome<Book>> {
        return this.change(bookId, { readingStatus: 'abandoned', finishedOn: TODAY });
    }

    rate(bookId: string, rating: number): Promise<Outcome<Book>> {
        return this.change(bookId, { rating });
    }

    lend(bookId: string, borrower: string): Promise<Outcome<Book>> {
        return this.change(bookId, { lentTo: borrower.trim(), lentOn: TODAY });
    }

    returnBook(bookId: string): Promise<Outcome<Book>> {
        return this.change(bookId, { lentTo: null, lentOn: null });
    }

    acquire(bookId: string): Promise<Outcome<Book>> {
        return this.change(bookId, { owned: true });
    }

    remove(bookId: string): Promise<Outcome<null>> {
        const refused = this.refused(bookId);

        if (refused !== undefined) {
            return Promise.resolve(refused);
        }

        this.books.delete(bookId);

        return Promise.resolve({ ok: true, value: null });
    }

    private change(bookId: string, effect: Partial<Book>): Promise<Outcome<Book>> {
        const refused = this.refused(bookId);
        const book = this.books.get(bookId);

        if (refused !== undefined || book === undefined) {
            return Promise.resolve(
                refused ?? { ok: false, refusal: { type: 'unknown-book', detail: 'No such book.' } },
            );
        }

        const changed = { ...book, ...effect };
        this.books.set(bookId, changed);

        return Promise.resolve({ ok: true, value: changed });
    }

    private refused(bookId: string): { ok: false; refusal: Refusal } | undefined {
        const refusal =
            this.refusal ?? (this.books.has(bookId) ? undefined : { type: 'unknown-book', detail: 'No such book.' });
        this.refusal = undefined;

        return refusal === undefined ? undefined : { ok: false, refusal };
    }

    private mentions(book: Book, text: string): boolean {
        const haystack = `${book.title} ${book.author}`.toLowerCase();

        return text
            .toLowerCase()
            .split(/\s+/)
            .every((word) => haystack.includes(word));
    }
}
