import type { Book } from '../core/model/book';
import styles from './BookList.module.css';
import { day, STATUS } from './format';
import { classes } from './classes';
import { Marks } from './Marks';

const reading = (book: Book): string => {
    if (book.startedOn !== null && book.finishedOn !== null) return `${day(book.startedOn)} → ${day(book.finishedOn)}`;
    if (book.startedOn !== null) return `depuis le ${day(book.startedOn)}`;
    if (book.finishedOn !== null) return `fini le ${day(book.finishedOn)}`;
    return '—';
};

const loan = (book: Book): string => {
    if (book.lentTo !== null && book.lentOn !== null) return `chez ${book.lentTo}, ${day(book.lentOn)}`;
    return book.owned ? 'à la maison' : 'pas encore à vous';
};

export function BookList({ books }: { books: readonly Book[] }) {
    if (books.length === 0) {
        return <p className={styles.empty}>Aucun livre ne correspond.</p>;
    }

    return (
        <table className={styles.list}>
            <thead>
                <tr>
                    <th scope="col">
                        <span className={styles.hidden}>En cours</span>
                    </th>
                    <th scope="col">Titre</th>
                    <th scope="col">Statut</th>
                    <th scope="col">Lecture</th>
                    <th scope="col">Note</th>
                    <th scope="col">Prêt</th>
                </tr>
            </thead>
            <tbody>
                {books.map((book) => (
                    <tr key={book.id}>
                        <td className={styles.signetCell}>
                            {book.readingStatus === 'reading' && (
                                <span className={styles.signet} aria-label="En cours" />
                            )}
                        </td>
                        <td className={styles.title}>
                            <strong>{book.title}</strong>
                            <span>{book.author}</span>
                        </td>
                        <td>
                            {book.owned ? (
                                <span className={classes(styles.status, styles[book.readingStatus])}>
                                    {STATUS[book.readingStatus]}
                                </span>
                            ) : (
                                <span className={styles.wish}>Envie</span>
                            )}
                        </td>
                        <td className={styles.mono}>{reading(book)}</td>
                        <td>{book.rating !== null && <Marks rating={book.rating} />}</td>
                        <td className={classes(styles.mono, book.lentTo !== null && styles.out)}>{loan(book)}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
