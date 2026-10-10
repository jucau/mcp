import { countShelves } from '../../../src/library/core/use-cases/count-shelves';
import { aBook } from '../../support/library/books';
import { InMemoryLibrary } from '../../support/library/in-memory-library';

describe('counting the books of each shelf', () => {
    it('counts every shelf, past the first page of the API', async () => {
        const toRead = Array.from({ length: 25 }, () => aBook());
        const library = new InMemoryLibrary(...toRead, aBook({ readingStatus: 'reading' }), aBook({ owned: false }));

        expect(await countShelves(library)()).toEqual({
            all: 27,
            reading: 1,
            'to-read': 25,
            read: 0,
            abandoned: 0,
            wishlist: 1,
        });
    });
});
