import type { Outcome } from '../model/refusal';
import type { Library } from '../ports/library';

export const removeBook =
    (library: Library) =>
    (bookId: string): Promise<Outcome<null>> =>
        library.remove(bookId);
