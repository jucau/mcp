import { createContext, use, type ReactNode } from 'react';
import type { Library } from '../core/ports/library';

const LibraryContext = createContext<Library | null>(null);

export function LibraryProvider({ library, children }: { library: Library; children: ReactNode }) {
    return <LibraryContext value={library}>{children}</LibraryContext>;
}

export function useLibrary(): Library {
    const library = use(LibraryContext);

    if (library === null) {
        throw new Error('useLibrary() needs a <LibraryProvider> above it.');
    }

    return library;
}
