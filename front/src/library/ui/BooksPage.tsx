import { useQuery } from '@tanstack/react-query';
import { searchBooks } from '../core/use-cases/search-books';
import { useLibrary } from './library-context';

export function BooksPage() {
    const library = useLibrary();
    const books = useQuery({ queryKey: ['books'], queryFn: () => searchBooks(library)({}) });

    if (books.isPending) {
        return <p>Chargement de la bibliothèque…</p>;
    }

    if (books.isError) {
        return <p role="alert">La bibliothèque est injoignable : {books.error.message}</p>;
    }

    return (
        <section>
            <h2>
                {books.data.total} {books.data.total > 1 ? 'livres' : 'livre'}
            </h2>
            <ul>
                {books.data.items.map((book) => (
                    <li key={book.id}>
                        {book.title} — {book.author}
                    </li>
                ))}
            </ul>
        </section>
    );
}
