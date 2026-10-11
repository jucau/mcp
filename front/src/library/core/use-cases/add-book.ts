import type { Book } from '../model/book';
import type { NewBook } from '../model/new-book';
import type { Outcome } from '../model/refusal';
import type { Library } from '../ports/library';

export const addBook =
    (library: Library) =>
    (book: NewBook): Promise<Outcome<Book>> =>
        library.add(book);
