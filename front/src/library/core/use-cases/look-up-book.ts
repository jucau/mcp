import type { CatalogEntry } from '../model/catalog-entry';
import type { Outcome } from '../model/refusal';
import type { Library } from '../ports/library';

export const lookUpBook =
    (library: Library) =>
    (isbn: string): Promise<Outcome<CatalogEntry>> =>
        library.lookUp(isbn.trim());
