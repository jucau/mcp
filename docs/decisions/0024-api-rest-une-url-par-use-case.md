# ADR-0024 — L'API REST parle métier : une URL par use case, et les refus en *Problem Details*

- **Statut** : Accepté (2026-10-10)

## Contexte

Une API REST devient le second adaptateur primaire du domaine ([09](../09-api-rest.md)). Les dix tools MCP sont
chacun **un use case** ([ADR-0008](./0008-tools-adaptateurs-minces-refus-metier.md)). REST strict pense en ressources
et en quatre verbes : sept de nos use cases (commencer, terminer, abandonner, noter, prêter, rendre, acquérir) sont
des **transitions** que le CRUD ne nomme pas. Il faudrait dire « supprimer le prêt » pour « rendre un livre ».

En MCP, un refus métier est un message que lit le modèle. En HTTP, le client est un programme qui branche sur un
statut : l'adaptateur doit classer les refus.

## Décision

- **Une URL par action, nommée comme le use case** : `POST /api/books/{id}/start-reading`, `…/lend`, `…/return`…
  L'`id` dans l'URL, les arguments dans un corps JSON, le livre mis à jour en réponse.
- **Les verbes HTTP gardés où ils sont les mots du métier** : `GET` pour chercher et consulter (livre, étagère),
  `POST /api/books` pour ajouter (`201` et `Location`), `DELETE /api/books/{id}` pour retirer (`204`).
- **Pas de version dans l'URL** : un seul client, déployé avec l'API.
- **Les refus du domaine deviennent des *Problem Details*** (RFC 9457) : `404` quand l'URL ne désigne aucun livre du
  lecteur, `409` quand l'état du livre interdit l'action, `422` quand une valeur fournie viole une règle du domaine
  ou que la forme est invalide. Le `detail` est le message du domaine, tel quel.
- **Un test vérifie que chaque `DomainException` du domaine a un statut.**
- **La forme se valide en strict**, comme dans les tools ; la query string, sans types, est convertie explicitement.

## Conséquences

- (+) Le langage ubiquitaire se lit dans les URL : un développeur qui connaît le domaine devine l'API.
- (+) Les lectures restent sûres et cachables, `DELETE` reste idempotent.
- (+) Le refus s'écrit une seule fois, dans le domaine ; MCP et HTTP n'y ajoutent que leur emballage.
- (−) Huit actions idempotentes dans le métier passent par `POST`, qui ne l'est pas : le protocole ne permet plus à un
  intermédiaire de les rejouer. Un `Idempotency-Key` le compenserait ; il n'est pas prévu.
- (−) Le classement des refus vit dans l'adaptateur, loin des exceptions : c'est le test exhaustif qui empêche
  l'oubli.

## Alternatives écartées

- **REST orienté ressources** (`PUT /books/{id}/loan`, `DELETE /books/{id}/loan`, `PUT /books/{id}/rating`) : plus
  conforme aux verbes, et l'idempotence s'y verrait, mais le métier y perd ses mots.
- **RPC intégral** (`POST /search-books`, `POST /remove-book`) : cohérent, mais il jette les garanties de `GET` sans
  qu'aucun argument métier ne le justifie.
- **Un corps d'erreur maison** : rien à y gagner face à un standard que les clients HTTP savent déjà lire.
- **Un statut porté par l'exception du domaine** : le domaine connaîtrait HTTP.
