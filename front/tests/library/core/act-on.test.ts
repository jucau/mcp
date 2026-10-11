import type { Book } from '../../../src/library/core/model/book';
import type { BookAction } from '../../../src/library/core/model/book-action';
import { actOn } from '../../../src/library/core/use-cases/act-on';
import { removeBook } from '../../../src/library/core/use-cases/remove-book';
import { aBook } from '../../support/library/books';
import { InMemoryLibrary, TODAY } from '../../support/library/in-memory-library';

describe('acting on a book', () => {
    it.each<[BookAction, Partial<Book>, Partial<Book>]>([
        [{ kind: 'start-reading' }, {}, { readingStatus: 'reading', startedOn: TODAY }],
        [{ kind: 'finish-reading' }, { readingStatus: 'reading' }, { readingStatus: 'read', finishedOn: TODAY }],
        [{ kind: 'abandon-reading' }, { readingStatus: 'reading' }, { readingStatus: 'abandoned', finishedOn: TODAY }],
        [{ kind: 'rate', rating: 4 }, { readingStatus: 'read' }, { rating: 4 }],
        [{ kind: 'lend', borrower: 'Marie' }, {}, { lentTo: 'Marie', lentOn: TODAY }],
        [{ kind: 'return' }, { lentTo: 'Paul', lentOn: '2026-07-03' }, { lentTo: null, lentOn: null }],
        [{ kind: 'acquire' }, { owned: false }, { owned: true }],
    ])('%o asks the library for its use case and gives back the updated book', async (action, before, after) => {
        const book = aBook(before);
        const library = new InMemoryLibrary(book);

        const outcome = await actOn(library)(book.id, action);

        expect(outcome).toEqual({ ok: true, value: { ...book, ...after } });
        expect(library.book(book.id)).toEqual({ ...book, ...after });
    });

    it('gives back the refusal of the library, and changes nothing', async () => {
        const book = aBook({ readingStatus: 'read' });
        const library = new InMemoryLibrary(book);
        library.refuseNext(
            'reading-transition-not-allowed',
            'Cannot start reading "Dune": its reading status is read.',
        );

        const outcome = await actOn(library)(book.id, { kind: 'start-reading' });

        expect(outcome).toEqual({
            ok: false,
            refusal: {
                type: 'reading-transition-not-allowed',
                detail: 'Cannot start reading "Dune": its reading status is read.',
            },
        });
        expect(library.book(book.id)).toEqual(book);
    });
});

describe('removing a book', () => {
    it('takes it out of the library', async () => {
        const book = aBook();
        const library = new InMemoryLibrary(book);

        expect(await removeBook(library)(book.id)).toEqual({ ok: true, value: null });
        expect(library.book(book.id)).toBeUndefined();
    });

    it('gives back the refusal of the library, and keeps the book', async () => {
        const book = aBook({ lentTo: 'Paul' });
        const library = new InMemoryLibrary(book);
        library.refuseNext('book-lent');

        expect(await removeBook(library)(book.id)).toEqual({
            ok: false,
            refusal: { type: 'book-lent', detail: 'Refused: book-lent.' },
        });
        expect(library.book(book.id)).toEqual(book);
    });
});
