import type { CatalogEntry } from '../../../src/library/core/model/catalog-entry';
import { lookUpRefusalMessage } from '../../../src/library/core/model/look-up-refusal-message';
import type { NewBook } from '../../../src/library/core/model/new-book';
import { prefilled } from '../../../src/library/core/model/prefilled';
import { lookUpBook } from '../../../src/library/core/use-cases/look-up-book';
import { InMemoryLibrary } from '../../support/library/in-memory-library';

const dune: CatalogEntry = {
    isbn: '9780441172719',
    title: 'Dune',
    author: 'Frank Herbert',
    publishedIn: 1965,
    pages: 896,
};

describe('looking a book up in the catalog', () => {
    it('gives back the entry of the ISBN typed, spaces around it left out', async () => {
        const library = new InMemoryLibrary();
        library.knows(dune);

        expect(await lookUpBook(library)(' 9780441172719 ')).toEqual({ ok: true, value: dune });
    });

    it('gives back the refusal of the library', async () => {
        const library = new InMemoryLibrary();
        library.knows(dune);
        library.refuseNext('catalog-unavailable');

        expect(await lookUpBook(library)('9780441172719')).toEqual({
            ok: false,
            refusal: { type: 'catalog-unavailable', detail: 'Refused: catalog-unavailable.' },
        });
    });

    it('says so when the catalog does not know the ISBN', async () => {
        expect(await lookUpBook(new InMemoryLibrary())('9780441172719')).toMatchObject({
            ok: false,
            refusal: { type: 'not-in-catalog' },
        });
    });
});

describe('filling the form from an entry of the catalog', () => {
    const typed: NewBook = { title: 'dune ?', author: 'herbert', isbn: '0-441-17271-7', owned: false };

    it('takes the title, the author and the ISBN of the entry, and keeps the rest', () => {
        expect(prefilled(typed, dune)).toEqual({
            title: 'Dune',
            author: 'Frank Herbert',
            isbn: '9780441172719',
            owned: false,
        });
    });

    it('keeps the author typed when the catalog names none', () => {
        expect(prefilled(typed, { ...dune, author: null })).toMatchObject({ title: 'Dune', author: 'herbert' });
    });
});

describe('telling the reader why a book could not be looked up', () => {
    it.each([
        ['not-in-catalog', "Le catalogue ne connaît pas l'ISBN 9780441172719 : saisissez le titre et l'auteur."],
        [
            'catalog-unavailable',
            "Le catalogue ne répond pas pour l'instant : saisissez le titre et l'auteur, ou réessayez plus tard.",
        ],
        [
            'invalid-isbn',
            "« 9780441172719 » n'est pas un ISBN valide : il en faut un à 10 ou 13 chiffres, tirets permis.",
        ],
    ])('%s', (type, message) => {
        expect(lookUpRefusalMessage({ type, detail: 'the detail of the API' }, ' 9780441172719 ')).toBe(message);
    });

    it('falls back on what the API said, for a refusal it does not know', () => {
        expect(lookUpRefusalMessage({ type: 'something-new', detail: 'Something new.' }, '9780441172719')).toBe(
            'Something new.',
        );
    });
});
