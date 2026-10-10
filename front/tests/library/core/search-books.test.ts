import type { Shelf } from '../../../src/library/core/model/shelf';
import { searchBooks } from '../../../src/library/core/use-cases/search-books';
import { aBook } from '../../support/library/books';
import { InMemoryLibrary } from '../../support/library/in-memory-library';

const library = new InMemoryLibrary(
    aBook({ title: 'Dune', readingStatus: 'read' }),
    aBook({ title: 'Hypérion', readingStatus: 'reading' }),
    aBook({ title: 'Neuromancien', lentTo: 'Léa' }),
    aBook({ title: 'Les Furtifs' }),
    aBook({ title: 'Ulysse', readingStatus: 'abandoned' }),
    aBook({ title: 'Le Sorceleur', owned: false }),
);

const titles = async (shelf: Shelf, text = ''): Promise<string[]> =>
    (await searchBooks(library)({ shelf, text, page: 1 })).items.map((book) => book.title);

describe('browsing the library', () => {
    it.each<[Shelf, string[]]>([
        ['all', ['Dune', 'Hypérion', 'Le Sorceleur', 'Les Furtifs', 'Neuromancien', 'Ulysse']],
        ['reading', ['Hypérion']],
        ['to-read', ['Les Furtifs', 'Neuromancien']],
        ['read', ['Dune']],
        ['abandoned', ['Ulysse']],
        ['wishlist', ['Le Sorceleur']],
    ])('the shelf %s holds its books, lent ones included', async (shelf, expected) => {
        expect(await titles(shelf)).toEqual(expected);
    });

    it('narrows a shelf to the books that match the words typed', async () => {
        expect(await titles('to-read', 'furtifs')).toEqual(['Les Furtifs']);
    });

    it('reads the page asked for', async () => {
        const books = Array.from({ length: 23 }, (_, index) =>
            aBook({ title: `Tome ${String(index + 1).padStart(2, '0')}` }),
        );

        const page = await searchBooks(new InMemoryLibrary(...books))({ shelf: 'all', text: '', page: 2 });

        expect(page.items.map((book) => book.title)).toEqual(['Tome 21', 'Tome 22', 'Tome 23']);
        expect(page.page).toBe(2);
    });
});
