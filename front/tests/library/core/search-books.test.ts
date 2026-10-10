import { searchBooks } from '../../../src/library/core/use-cases/search-books';
import { aBook } from '../../support/library/books';
import { InMemoryLibrary } from '../../support/library/in-memory-library';

describe('searching books', () => {
    it('finds the books of the library that match every criterion', async () => {
        const wished = aBook({ title: 'Hypérion', author: 'Dan Simmons', owned: false });
        const search = searchBooks(new InMemoryLibrary(aBook({ title: 'Dune' }), wished));

        const page = await search({ text: 'simmons', owned: false });

        expect(page).toEqual({ items: [wished], page: 1, perPage: 20, total: 1 });
    });
});
