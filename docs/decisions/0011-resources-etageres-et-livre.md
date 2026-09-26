# ADR-0011 — Les resources : quatre étagères fixes, un livre par template, les refus transmis

- **Statut** : Accepté (2026-09-27)

## Contexte

Les tools exposent tous les use cases. La deuxième primitive, la resource, expose des **données** que le host
met dans le contexte ([04](../04-les-trois-primitives.md)). Il faut choisir quelles données, sous quelles URI,
dans quel format, et comment elles échouent.

## Décision

- **Quatre resources à URI fixe, les étagères** : `library://shelves/{to-read,reading,lent,wishlist}`. Ce sont
  des recherches pré-filtrées. Chaque étagère renvoie **tous** ses livres, sans pagination.
- **Un template d'URI pour un livre** : `library://books/{id}`.
- **Contenu en JSON** (`application/json`), au format de `BookPresenter`, le même que celui des tools.
- **Les resources étendent `LibraryResource`**, qui traduit toute `DomainException` en `Response::error()`,
  comme `LibraryTool` ([ADR-0008](./0008-tools-adaptateurs-minces-refus-metier.md)). Le SDK en fait une erreur
  JSON-RPC qui garde son message, même en production.
- **Pas d'annotations de resource** : aucune n'apporterait d'information.

## Conséquences

- (+) L'utilisateur peut joindre une étagère à sa question (`@`-mention dans Claude Code) sans que le modèle ait
  à chercher.
- (+) Les resources passent par la même lecture que les tools (`BookQueries`) : aucune règle dupliquée.
- (−) Une étagère très remplie serait lue en entier. Pour une bibliothèque personnelle, c'est acceptable.
- (−) Le code d'erreur est `-32603` et non `-32002` comme le recommande la spec : le SDK ne permet pas de le
  choisir.

## Alternatives écartées

- **Un template `library://shelves/{shelf}`** : les étagères disparaîtraient de `resources/list`, et le host ne
  pourrait plus les proposer d'emblée à l'utilisateur.
- **Des étagères paginées** (`?page=2`) : une resource se lit d'un bloc. Une liste à parcourir par pages est le
  rôle de `search_books`.
- **Un contenu en Markdown** : plus agréable à lire pour un humain, mais les tools renvoient déjà du JSON, et un
  seul format par concept (ADR-0008) vaut mieux que deux.
