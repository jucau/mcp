# ADR-0029 — Un hexagone côté front, sans y recopier le domaine

- **Statut** : Accepté (2026-10-10)

## Contexte

Le frontend React ([10](../10-frontend.md)) consomme l'API REST. Le domaine (`Book` et ses invariants) vit dans le
back ([ADR-0006](./0006-architecture-hexagonale-ciblee.md)). On veut montrer qu'une architecture hexagonale a du sens
côté client, sans savoir encore ce qu'y serait le « domaine ».

## Décision

- **Pas de domaine au sens du back.** Aucune règle métier n'est recopiée en TypeScript : le serveur reste la seule
  source de vérité, et ses refus arrivent au client par les *Problem Details*.
- **Un cœur** (`core/`) : le **modèle de lecture** (`Book`, `ReadingStatus`, `Refusal`), les **ports** (`Library`,
  `Session`) et les **use cases** du client. Il ne connaît ni React, ni `fetch`, ni le navigateur.
- **Un adaptateur secondaire** : HTTP, typé par le contrat ([ADR-0032](./0032-client-http-type-par-le-contrat.md)).
- **Les tests et le double en mémoire vivent hors de `src/`** (`tests/`, `tests/support/`), comme côté back.
- **React est l'adaptateur primaire**, comme les tools MCP et les contrôleurs côté back. L'état serveur (TanStack
  Query) y vit, jamais dans le cœur.
- **Une racine de composition** (`app/`) est le seul endroit qui branche un adaptateur sur un port.
- **Les couches sont vérifiées** par la règle `no-restricted-imports` d'ESLint, couche par couche, dans la porte, comme
  PHPArkitect côté back. `eslint-plugin-boundaries` a été écarté : il tirait des dépendances vulnérables, dont une
  critique.
- **Le double en mémoire ne contient aucune règle** : il garde des livres et oppose les refus qu'on lui programme.
- **Les use cases se testent avec le double**, et Stryker exige un MSI de 100 % sur le cœur, comme Infection côté back.

## Conséquences

- (+) Les use cases se testent sans navigateur ni réseau, et l'écran ne contient pas de logique.
- (+) Une seule source de vérité pour les règles : un changement du domaine ne demande rien au front.
- (+) Le contraste avec le back est pédagogique : un cœur peut exister sans invariant.
- (−) Le cœur est mince, et plusieurs use cases ne font qu'appeler le port : c'est le prix d'une frontière tenue.
- (−) Le double programmé ressemble à un stub : c'est le seul moyen de tester un refus sans recopier la règle.

## Alternatives écartées

- **Recopier les invariants dans le front** (un `Book` qui refuse lui-même un prêt) : deux sources de vérité, qui
  divergeraient.
- **Pas d'architecture** (des composants qui appellent `fetch`) : rapide, mais rien ne se teste sans réseau, et la
  logique se disperse dans les écrans.
- **Un double en mémoire qui simule les règles** : il faudrait les recopier, ce qu'on refuse justement.
