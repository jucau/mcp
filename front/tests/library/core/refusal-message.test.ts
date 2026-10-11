import type { Book } from '../../../src/library/core/model/book';
import type { BookActionKind } from '../../../src/library/core/model/book-action';
import { refusalMessage } from '../../../src/library/core/model/refusal-message';
import { aBook } from '../../support/library/books';

describe('telling the reader why the library refused', () => {
    it.each<[string, Partial<Book>, BookActionKind | 'remove', string]>([
        [
            'reading-transition-not-allowed',
            { readingStatus: 'read' },
            'start-reading',
            'Impossible de commencer « Dune » : il est déjà lu.',
        ],
        [
            'reading-transition-not-allowed',
            { readingStatus: 'to_read' },
            'abandon-reading',
            "Impossible d'abandonner « Dune » : il n'est pas commencé.",
        ],
        [
            'reading-transition-not-allowed',
            { readingStatus: 'reading' },
            'start-reading',
            'Impossible de commencer « Dune » : il est en cours.',
        ],
        [
            'reading-transition-not-allowed',
            { readingStatus: 'abandoned' },
            'finish-reading',
            'Impossible de terminer « Dune » : il est abandonné.',
        ],
        ['finished-before-started', {}, 'finish-reading', "« Dune » ne peut pas finir avant d'avoir commencé."],
        ['book-not-finished', {}, 'rate', "« Dune » ne se note qu'une fois lu ou abandonné."],
        ['book-not-owned', {}, 'lend', "« Dune » est sur la liste d'envies : on ne prête pas un livre qu'on n'a pas."],
        ['book-already-lent', { lentTo: 'Paul' }, 'lend', '« Dune » est déjà prêté à Paul.'],
        ['book-already-lent', {}, 'lend', "« Dune » est déjà prêté à quelqu'un."],
        ['book-not-lent', {}, 'return', "« Dune » n'est pas prêté : il n'y a rien à rendre."],
        ['book-lent', { lentTo: 'Paul' }, 'remove', '« Dune » est prêté à Paul : récupérez-le avant de le retirer.'],
        ['book-lent', {}, 'remove', "« Dune » est prêté à quelqu'un : récupérez-le avant de le retirer."],
        [
            'book-changed-meanwhile',
            {},
            'lend',
            "« Dune » vient d'être modifié ailleurs : rechargez la page avant de recommencer.",
        ],
        ['unknown-book', {}, 'return', "« Dune » n'est plus dans la bibliothèque."],
        ['invalid-book-id', {}, 'acquire', "« Dune » n'est plus dans la bibliothèque."],
    ])('%s, %o, %s', (type, book, attempted, message) => {
        expect(
            refusalMessage({ type, detail: 'the detail of the domain' }, aBook({ title: 'Dune', ...book }), attempted),
        ).toBe(message);
    });

    it('falls back on what the domain said, for a reading refusal no reading action caused', () => {
        expect(
            refusalMessage({ type: 'reading-transition-not-allowed', detail: 'Cannot rate "Dune".' }, aBook(), 'rate'),
        ).toBe('Cannot rate "Dune".');
    });

    it('falls back on what the domain said, for a refusal it does not know', () => {
        expect(refusalMessage({ type: 'invalid-text', detail: 'A borrower cannot be blank.' }, aBook(), 'lend')).toBe(
            'A borrower cannot be blank.',
        );
    });
});
