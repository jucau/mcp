# ADR-0033 — Le front traduit les refus par leur type, et retombe sur le message du domaine

- **Statut** : Accepté (2026-10-11). Complète l'[ADR-0031](./0031-tenter-puis-afficher-le-refus.md), qui affichait
  le `detail` tel quel.

## Contexte

L'écran affiche le refus de l'API ([ADR-0031](./0031-tenter-puis-afficher-le-refus.md)). Le `detail` d'un *Problem
Details* est le message du domaine, écrit en anglais comme tout le code
([ADR-0001](./0001-code-en-anglais-prose-en-francais.md)) : « Cannot start reading "Dune": its reading status is
read. » L'écran, lui, parle français. Afficher le `detail` mélangerait les deux langues.

Le `type` d'un refus, lui, est stable et documenté dans le contrat OpenAPI
([ADR-0028](./0028-openapi-genere-par-scramble-verifie-par-spectator.md)) : `book-already-lent`,
`reading-transition-not-allowed`…

## Décision

- **Le front traduit chaque refus qu'il connaît par son `type`**, avec ce qu'il sait du livre (son titre, son
  emprunteur) et l'action tentée : « Impossible de commencer « Dune » : il est déjà lu. »
- **Pour un type qu'il ne connaît pas, il affiche le `detail`**, le message du domaine. Un refus nouveau reste lisible
  avant d'être traduit.
- **La traduction vit dans le cœur du front** (`refusalMessage`) : elle est pure, et Stryker la juge.
- **L'adaptateur HTTP ne garde du `type` que son dernier segment** (`book-already-lent`), indépendant de l'hôte.

## Conséquences

- (+) Une seule langue à l'écran, et le message du domaine reste la référence pour un refus inconnu.
- (+) Ce n'est pas une règle métier : la table dit comment nommer un refus, jamais quand il arrive.
- (−) Une liste à tenir à jour quand le domaine ajoute un refus. Le repli sur le `detail` limite le coût d'un oubli
  à un message en anglais.

## Alternatives écartées

- **Le `detail` tel quel** : deux langues à l'écran.
- **Des messages traduits par le back** (négociation par `Accept-Language`) : le domaine écrirait ses refus dans
  chaque langue de ses clients, alors qu'il n'en connaît aucun.
