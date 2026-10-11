import { addRefusalMessage } from '../../../src/library/core/model/add-refusal-message';
import type { NewBook } from '../../../src/library/core/model/new-book';
import { addBook } from '../../../src/library/core/use-cases/add-book';
import { InMemoryLibrary } from '../../support/library/in-memory-library';

const dune: NewBook = { title: 'Dune', author: 'Frank Herbert', isbn: ' 9780441172719 ', owned: true };

describe('adding a book', () => {
    it('gives back the book the library added', async () => {
        const library = new InMemoryLibrary();

        const outcome = await addBook(library)(dune);

        expect(outcome).toMatchObject({
            ok: true,
            value: { title: 'Dune', author: 'Frank Herbert', isbn: '9780441172719', owned: true },
        });
        expect(outcome.ok && library.book(outcome.value.id)).toMatchObject({ title: 'Dune' });
    });

    it('gives back the refusal of the library, and adds nothing', async () => {
        const library = new InMemoryLibrary();
        library.refuseNext('duplicate-isbn');

        expect(await addBook(library)(dune)).toEqual({
            ok: false,
            refusal: { type: 'duplicate-isbn', detail: 'Refused: duplicate-isbn.' },
        });
        expect((await library.search({})).total).toBe(0);
    });
});

describe('telling the reader why a book could not be added', () => {
    it.each([
        [
            'duplicate-isbn',
            "Un livre avec l'ISBN 9780441172719 est déjà dans la bibliothèque : cherchez-le plutôt que de l'ajouter deux fois.",
        ],
        [
            'invalid-isbn',
            "« 9780441172719 » n'est pas un ISBN valide : il en faut un à 10 ou 13 chiffres, tirets permis.",
        ],
        ['invalid-text', "Un livre a besoin d'un titre et d'un auteur."],
        ['invalid-request', "Un livre a besoin d'un titre et d'un auteur."],
    ])('%s', (type, message) => {
        expect(addRefusalMessage({ type, detail: 'the detail of the domain' }, dune)).toBe(message);
    });

    it('falls back on what the domain said, for a refusal it does not know', () => {
        expect(addRefusalMessage({ type: 'something-new', detail: 'Something new.' }, dune)).toBe('Something new.');
    });
});
