# ADR-0031 — L'écran propose les actions, et c'est l'API qui refuse

- **Statut** : Accepté (2026-10-10). L'affichage du `detail` tel quel est complété par
  [ADR-0033](./0033-refus-traduits-par-leur-type.md).

## Contexte

Sans règle côté client ([ADR-0029](./0029-hexagone-front-sans-domaine-duplique.md)), l'écran ne sait pas qu'un livre
déjà lu ne peut pas être commencé, ni qu'un livre à la maison ne peut pas être rendu. Choisir les boutons selon l'état
du livre, ce serait recopier les transitions du domaine.

## Décision

- **L'écran propose toutes les actions** d'un livre.
- **L'API décide.** Un refus arrive en *Problem Details* ([ADR-0024](./0024-api-rest-une-url-par-use-case.md)), et
  l'écran affiche son `detail`, le message écrit par le domaine.
- **L'évolution prévue** : l'API annoncera les actions possibles de chaque livre, calculées par le domaine, le jour
  où le coût ergonomique gênera.

## Conséquences

- (+) Une seule source de vérité, et le message du domaine sert enfin un humain.
- (+) Rien à changer côté back.
- (−) Des boutons qui mènent à un refus : un « Rendre » sur un livre qui n'est pas prêté.

## Alternatives écartées

- **Masquer les actions selon l'état** : meilleur pour l'ergonomie, mais c'est recopier les transitions du domaine.
- **Annoncer les actions dans l'API dès maintenant** : la bonne cible, mais un changement d'API et de contrat avant
  même d'avoir un écran.
