import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { useDeferredValue, useState } from 'react';
import { pageCount } from '../core/model/page';
import type { Shelf } from '../core/model/shelf';
import { countShelves } from '../core/use-cases/count-shelves';
import { searchBooks } from '../core/use-cases/search-books';
import { BookList } from './BookList';
import { books } from './format';
import { useLibrary } from './library-context';
import styles from './LibraryPage.module.css';
import { Pagination } from './Pagination';
import { ShelfFilters } from './ShelfFilters';

export function LibraryPage() {
    const library = useLibrary();
    const [shelf, setShelf] = useState<Shelf>('all');
    const [typed, setTyped] = useState('');
    const [page, setPage] = useState(1);
    const text = useDeferredValue(typed.trim());

    const counts = useQuery({ queryKey: ['shelves'], queryFn: countShelves(library) });
    const found = useQuery({
        queryKey: ['books', shelf, text, page],
        queryFn: () => searchBooks(library)({ shelf, text, page }),
        placeholderData: keepPreviousData,
    });

    const browse = (next: { shelf?: Shelf; text?: string }) => {
        if (next.shelf !== undefined) setShelf(next.shelf);
        if (next.text !== undefined) setTyped(next.text);
        setPage(1);
    };

    return (
        <section className={styles.library} aria-label="La bibliothèque">
            <search className={styles.search}>
                <input
                    id="search"
                    type="search"
                    value={typed}
                    placeholder="Un titre, un auteur…"
                    aria-label="Chercher un livre"
                    autoComplete="off"
                    onChange={(event) => {
                        browse({ text: event.target.value });
                    }}
                />
            </search>

            <ShelfFilters
                current={shelf}
                counts={counts.data}
                onChoose={(chosen) => {
                    browse({ shelf: chosen });
                }}
            />

            {found.isPending ? (
                <p className={styles.note}>Chargement de la bibliothèque…</p>
            ) : found.isError ? (
                <p className={styles.note} role="alert">
                    La bibliothèque est injoignable : {found.error.message}
                </p>
            ) : (
                <>
                    <p className={styles.summary} aria-live="polite">
                        {books(found.data.total)} · triés par titre
                    </p>
                    <BookList books={found.data.items} />
                    <Pagination page={found.data.page} pages={pageCount(found.data)} onTurn={setPage} />
                </>
            )}
        </section>
    );
}
