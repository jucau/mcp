import type { Book } from '../../../src/library/core/model/book';

let sequence = 0;

export const aBook = (book: Partial<Book> = {}): Book => ({
    id: `00000000-0000-4000-8000-${String(++sequence).padStart(12, '0')}`,
    title: 'Dune',
    author: 'Frank Herbert',
    isbn: null,
    owned: true,
    readingStatus: 'to_read',
    startedOn: null,
    finishedOn: null,
    rating: null,
    lentTo: null,
    lentOn: null,
    ...book,
});
