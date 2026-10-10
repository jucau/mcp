import { SHELVES, type Shelf } from '../core/model/shelf';
import { SHELF } from './format';
import styles from './ShelfFilters.module.css';

interface Props {
    readonly current: Shelf;
    readonly counts: Record<Shelf, number> | undefined;
    readonly onChoose: (shelf: Shelf) => void;
}

export function ShelfFilters({ current, counts, onChoose }: Props) {
    return (
        <nav className={styles.filters} aria-label="Étagères">
            {SHELVES.map((shelf) => (
                <button
                    key={shelf}
                    type="button"
                    className={styles.chip}
                    aria-pressed={shelf === current}
                    onClick={() => {
                        onChoose(shelf);
                    }}
                >
                    {SHELF[shelf]}
                    {counts !== undefined && (
                        <>
                            {' '}
                            <span className={styles.count}>{counts[shelf]}</span>
                        </>
                    )}
                </button>
            ))}
        </nav>
    );
}
