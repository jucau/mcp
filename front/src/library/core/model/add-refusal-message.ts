import type { NewBook } from './new-book';
import type { Refusal } from './refusal';

export const addRefusalMessage = (refusal: Refusal, book: NewBook): string => {
    switch (refusal.type) {
        case 'duplicate-isbn':
            return `Un livre avec l'ISBN ${book.isbn.trim()} est déjà dans la bibliothèque : cherchez-le plutôt que de l'ajouter deux fois.`;
        case 'invalid-isbn':
            return `« ${book.isbn.trim()} » n'est pas un ISBN valide : il en faut un à 10 ou 13 chiffres, tirets permis.`;
        case 'invalid-text':
        case 'invalid-request':
            return "Un livre a besoin d'un titre et d'un auteur.";
        default:
            return refusal.detail;
    }
};
