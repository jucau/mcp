import type { ReadingStatus } from '../core/model/book';
import type { Shelf } from '../core/model/shelf';

const dates = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' });

export const day = (date: string): string => dates.format(new Date(`${date}T00:00:00Z`));

export const books = (count: number): string => `${String(count)} ${count > 1 ? 'livres' : 'livre'}`;

export const STATUS: Record<ReadingStatus, string> = {
    to_read: 'À lire',
    reading: 'En cours',
    read: 'Lu',
    abandoned: 'Abandonné',
};

export const SHELF: Record<Shelf, string> = {
    all: 'Toute la bibliothèque',
    reading: 'En cours',
    'to-read': 'À lire',
    read: 'Lus',
    abandoned: 'Abandonnés',
    wishlist: "Liste d'envies",
};
