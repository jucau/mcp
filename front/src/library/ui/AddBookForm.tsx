import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useId, useState } from 'react';
import { addRefusalMessage } from '../core/model/add-refusal-message';
import type { Book } from '../core/model/book';
import type { NewBook } from '../core/model/new-book';
import { addBook } from '../core/use-cases/add-book';
import styles from './AddBookForm.module.css';
import { useLibrary } from './library-context';

interface Props {
    readonly onAdded: (book: Book) => void;
    readonly onCancel: () => void;
}

export function AddBookForm({ onAdded, onCancel }: Props) {
    const library = useLibrary();
    const queryClient = useQueryClient();
    const id = useId();
    const [book, setBook] = useState<NewBook>({ title: '', author: '', isbn: '', owned: true });
    const [refusal, setRefusal] = useState<string>();

    const add = useMutation({
        mutationFn: (submitted: NewBook) => addBook(library)(submitted),
        onSuccess: async (outcome, submitted) => {
            if (outcome.ok) {
                await queryClient.invalidateQueries();
                onAdded(outcome.value);
            } else {
                setRefusal(addRefusalMessage(outcome.refusal, submitted));
            }
        },
    });

    const field = (name: 'title' | 'author' | 'isbn', label: string, required: boolean) => (
        <div className={styles.field}>
            <label htmlFor={`${id}-${name}`}>{label}</label>
            <input
                id={`${id}-${name}`}
                value={book[name]}
                required={required}
                autoComplete="off"
                onChange={(event) => {
                    setBook({ ...book, [name]: event.target.value });
                }}
            />
        </div>
    );

    return (
        <form
            className={styles.form}
            aria-label="Ajouter un livre"
            onSubmit={(event) => {
                event.preventDefault();
                setRefusal(undefined);
                add.mutate(book);
            }}
        >
            <h2>Ajouter un livre</h2>
            {field('title', 'Titre', true)}
            {field('author', 'Auteur', true)}
            {field('isbn', 'ISBN (facultatif)', false)}

            <fieldset className={styles.owned}>
                <legend>Ce livre</legend>
                <label>
                    <input
                        type="radio"
                        name={`${id}-owned`}
                        checked={book.owned}
                        onChange={() => {
                            setBook({ ...book, owned: true });
                        }}
                    />
                    je l&apos;ai déjà
                </label>
                <label>
                    <input
                        type="radio"
                        name={`${id}-owned`}
                        checked={!book.owned}
                        onChange={() => {
                            setBook({ ...book, owned: false });
                        }}
                    />
                    va sur ma liste d&apos;envies
                </label>
            </fieldset>

            {refusal !== undefined && (
                <p className={styles.slip} role="status">
                    {refusal}
                </p>
            )}

            <div className={styles.buttons}>
                <button type="submit" className={styles.primary} disabled={add.isPending}>
                    Ajouter
                </button>
                <button type="button" onClick={onCancel}>
                    Annuler
                </button>
            </div>
        </form>
    );
}
