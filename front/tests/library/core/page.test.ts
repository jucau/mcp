import { pageCount } from '../../../src/library/core/model/page';

describe('counting pages', () => {
    it.each([
        [0, 20, 1],
        [1, 20, 1],
        [20, 20, 1],
        [21, 20, 2],
        [23, 20, 2],
        [40, 20, 2],
    ])('%i books by %i make %i page(s)', (total, perPage, pages) => {
        expect(pageCount({ items: [], page: 1, perPage, total })).toBe(pages);
    });
});
