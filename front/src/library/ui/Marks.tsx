import styles from './Marks.module.css';

export function Marks({ rating }: { rating: number | null }) {
    return (
        <span
            className={styles.marks}
            role="img"
            aria-label={rating === null ? 'Pas de note' : `${String(rating)} sur 5`}
        >
            {[1, 2, 3, 4, 5].map((mark) => (
                <i key={mark} className={rating !== null && mark <= rating ? styles.on : undefined} />
            ))}
        </span>
    );
}
