import styles from './Pagination.module.css';

interface Props {
    readonly page: number;
    readonly pages: number;
    readonly onTurn: (page: number) => void;
}

export function Pagination({ page, pages, onTurn }: Props) {
    if (pages === 1) {
        return null;
    }

    return (
        <nav className={styles.pagination} aria-label="Pages">
            <button
                type="button"
                disabled={page === 1}
                onClick={() => {
                    onTurn(page - 1);
                }}
            >
                Précédente
            </button>
            <span aria-current="page">
                Page {page} sur {pages}
            </span>
            <button
                type="button"
                disabled={page === pages}
                onClick={() => {
                    onTurn(page + 1);
                }}
            >
                Suivante
            </button>
        </nav>
    );
}
