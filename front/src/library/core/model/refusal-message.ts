import type { Book } from './book';
import type { BookActionKind } from './book-action';
import type { Refusal } from './refusal';

const impossible: Partial<Record<BookActionKind | 'remove', string>> = {
    'start-reading': 'Impossible de commencer',
    'finish-reading': 'Impossible de terminer',
    'abandon-reading': "Impossible d'abandonner",
};

const states: Record<Book['readingStatus'], string> = {
    to_read: "il n'est pas commencé",
    reading: 'il est en cours',
    read: 'il est déjà lu',
    abandoned: 'il est abandonné',
};

export const refusalMessage = (refusal: Refusal, book: Book, attempted: BookActionKind | 'remove'): string => {
    const title = `« ${book.title} »`;

    switch (refusal.type) {
        case 'reading-transition-not-allowed': {
            const verb = impossible[attempted];

            return verb === undefined ? refusal.detail : `${verb} ${title} : ${states[book.readingStatus]}.`;
        }
        case 'finished-before-started':
            return `${title} ne peut pas finir avant d'avoir commencé.`;
        case 'book-not-finished':
            return `${title} ne se note qu'une fois lu ou abandonné.`;
        case 'book-not-owned':
            return `${title} est sur la liste d'envies : on ne prête pas un livre qu'on n'a pas.`;
        case 'book-already-lent':
            return `${title} est déjà prêté à ${book.lentTo ?? "quelqu'un"}.`;
        case 'book-not-lent':
            return `${title} n'est pas prêté : il n'y a rien à rendre.`;
        case 'book-lent':
            return `${title} est prêté à ${book.lentTo ?? "quelqu'un"} : récupérez-le avant de le retirer.`;
        case 'unknown-book':
        case 'invalid-book-id':
            return `${title} n'est plus dans la bibliothèque.`;
        default:
            return refusal.detail;
    }
};
