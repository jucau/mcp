import type { Refusal } from './refusal';

export const lookUpRefusalMessage = (refusal: Refusal, isbn: string): string => {
    switch (refusal.type) {
        case 'not-in-catalog':
            return `Le catalogue ne connaît pas l'ISBN ${isbn.trim()} : saisissez le titre et l'auteur.`;
        case 'catalog-unavailable':
            return "Le catalogue ne répond pas pour l'instant : saisissez le titre et l'auteur, ou réessayez plus tard.";
        case 'invalid-isbn':
            return `« ${isbn.trim()} » n'est pas un ISBN valide : il en faut un à 10 ou 13 chiffres, tirets permis.`;
        default:
            return refusal.detail;
    }
};
