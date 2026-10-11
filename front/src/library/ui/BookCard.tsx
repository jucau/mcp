import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useId, useState } from 'react';
import type { Book } from '../core/model/book';
import type { BookAction, BookActionKind } from '../core/model/book-action';
import type { Outcome } from '../core/model/refusal';
import { refusalMessage } from '../core/model/refusal-message';
import { actOn } from '../core/use-cases/act-on';
import { removeBook } from '../core/use-cases/remove-book';
import styles from './BookCard.module.css';
import { classes } from './classes';
import { day, STATUS } from './format';
import { useLibrary } from './library-context';
import { Marks } from './Marks';

interface Props {
    readonly book: Book;
    readonly onChange: (book: Book) => void;
    readonly onRemove: () => void;
}

type Slip = { readonly ok: boolean; readonly text: string } | undefined;

const DONE: Record<BookActionKind, (book: Book) => string> = {
    'start-reading': () => 'Lecture commencée.',
    'finish-reading': () => 'Lecture terminée.',
    'abandon-reading': () => 'Lecture abandonnée.',
    rate: (book) => `Noté ${String(book.rating)} sur 5.`,
    lend: (book) => `Prêté à ${book.lentTo ?? ''}.`,
    return: () => 'Rendu : il est de retour à la maison.',
    acquire: () => 'Il est à vous.',
};

export function BookCard({ book, onChange, onRemove }: Props) {
    const library = useLibrary();
    const queryClient = useQueryClient();
    const borrowerId = useId();
    const [slip, setSlip] = useState<Slip>();
    const [borrower, setBorrower] = useState('');
    const [confirming, setConfirming] = useState(false);

    const refresh = () => queryClient.invalidateQueries();

    const act = useMutation({
        mutationFn: (action: BookAction) => actOn(library)(book.id, action),
        onSuccess: async (outcome: Outcome<Book>, action) => {
            if (outcome.ok) {
                setSlip({ ok: true, text: DONE[action.kind](outcome.value) });
                setBorrower('');
                onChange(outcome.value);
                await refresh();
            } else {
                setSlip({ ok: false, text: refusalMessage(outcome.refusal, book, action.kind) });
            }
        },
    });

    const remove = useMutation({
        mutationFn: () => removeBook(library)(book.id),
        onSuccess: async (outcome) => {
            setConfirming(false);

            if (outcome.ok) {
                onRemove();
                await refresh();
            } else {
                setSlip({ ok: false, text: refusalMessage(outcome.refusal, book, 'remove') });
            }
        },
    });

    const busy = act.isPending || remove.isPending;
    const button = (label: string, action: BookAction) => (
        <button
            type="button"
            disabled={busy}
            onClick={() => {
                act.mutate(action);
            }}
        >
            {label}
        </button>
    );

    return (
        <article className={styles.card} aria-label={`Fiche du livre « ${book.title} »`}>
            <div className={styles.call}>
                <span>Fiche</span>
                <span>{book.isbn === null ? 'Sans ISBN' : `ISBN ${book.isbn}`}</span>
            </div>
            <h2 className={styles.title}>{book.title}</h2>
            <p className={styles.author}>{book.author}</p>

            <dl className={styles.facts}>
                <dt>Statut</dt>
                <dd>
                    <span className={classes(styles.status, styles[book.readingStatus])}>
                        {STATUS[book.readingStatus]}
                    </span>
                    {!book.owned && " · liste d'envies"}
                </dd>
                <dt>Commencé</dt>
                <dd>{book.startedOn === null ? '—' : <span className={styles.stamp}>{day(book.startedOn)}</span>}</dd>
                <dt>Fini</dt>
                <dd>{book.finishedOn === null ? '—' : <span className={styles.stamp}>{day(book.finishedOn)}</span>}</dd>
                <dt>Note</dt>
                <dd>
                    <Marks rating={book.rating} />
                </dd>
                <dt>Prêt</dt>
                <dd>
                    {book.lentTo !== null && book.lentOn !== null
                        ? `à ${book.lentTo}, le ${day(book.lentOn)}`
                        : 'à la maison'}
                </dd>
            </dl>

            <section className={styles.group} aria-label="Lecture">
                <h3>Lecture</h3>
                <div className={styles.buttons}>
                    {button('Commencer', { kind: 'start-reading' })}
                    {button('Terminer', { kind: 'finish-reading' })}
                    {button('Abandonner', { kind: 'abandon-reading' })}
                </div>
                <div className={styles.buttons} role="group" aria-label="Noter">
                    {[1, 2, 3, 4, 5].map((rating) => (
                        <button
                            key={rating}
                            type="button"
                            className={styles.rating}
                            disabled={busy}
                            aria-label={`Noter ${String(rating)} sur 5`}
                            onClick={() => {
                                act.mutate({ kind: 'rate', rating });
                            }}
                        >
                            {rating}
                        </button>
                    ))}
                </div>
            </section>

            <section className={styles.group} aria-label="Prêt et achat">
                <h3>Prêt et achat</h3>
                <form
                    className={styles.lend}
                    onSubmit={(event) => {
                        event.preventDefault();
                        act.mutate({ kind: 'lend', borrower });
                    }}
                >
                    <label htmlFor={borrowerId}>Emprunteur</label>
                    <input
                        id={borrowerId}
                        value={borrower}
                        onChange={(event) => {
                            setBorrower(event.target.value);
                        }}
                        placeholder="Marie"
                    />
                    <button type="submit" disabled={busy}>
                        Prêter
                    </button>
                </form>
                <div className={styles.buttons}>
                    {button('Rendre', { kind: 'return' })}
                    {button('Acquérir', { kind: 'acquire' })}
                </div>
            </section>

            <section className={styles.group} aria-label="Retrait">
                {confirming ? (
                    <div className={styles.confirm} role="alertdialog" aria-label="Retirer ce livre">
                        <p>Retirer « {book.title} » pour de bon, avec ses dates, sa note et son prêt ?</p>
                        <div className={styles.buttons}>
                            <button
                                type="button"
                                className={styles.danger}
                                disabled={busy}
                                onClick={() => {
                                    remove.mutate();
                                }}
                            >
                                Retirer définitivement
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    setConfirming(false);
                                }}
                            >
                                Garder
                            </button>
                        </div>
                    </div>
                ) : (
                    <button
                        type="button"
                        className={styles.quiet}
                        disabled={busy}
                        onClick={() => {
                            setConfirming(true);
                        }}
                    >
                        Retirer de la bibliothèque
                    </button>
                )}
            </section>

            {slip !== undefined && (
                <p className={classes(styles.slip, slip.ok && styles.ok)} role="status">
                    {slip.text}
                </p>
            )}
        </article>
    );
}
