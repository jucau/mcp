# ADR-0034 — Le catalogue ouvert est un port, et `lookup_book` un tool séparé

- **Statut** : Accepté (2026-10-11)

## Contexte

Ajouter un livre demande un titre et un auteur, que le lecteur connaît rarement par cœur quand il a le livre en main :
il a son ISBN. Un catalogue public comme Open Library sait retrouver le reste. C'est aussi l'occasion d'exercer
`openWorldHint` à `true`, que nos dix tools déclarent à `false`
([ADR-0009](./0009-quatre-annotations-explicites-par-tool.md)).

Deux questions : où placer cet appel, et que faire de ses échecs, qui ne sont pas des refus du domaine.

## Décision

- **Un tool séparé, en lecture seule** : `lookup_book(isbn)`, `readOnlyHint` et `openWorldHint` à `true`. `add_book`
  ne change pas et reste en monde fermé. Le modèle enchaîne les deux.
- **Le catalogue est un port de la couche application**, `BookCatalog`, comme `BookQueries` : c'est une lecture, sans
  agrégat. `OpenLibraryBookCatalog` l'implémente en infrastructure.
- **Un use case**, `LookUpBookHandler` : il valide et normalise l'ISBN avec l'objet valeur du domaine, puis interroge
  le port. Les trois adaptateurs primaires (tool MCP, API REST, front) passent par lui.
- **Deux exceptions de la couche application**, `NotInCatalog` et `CatalogUnavailable`. Ce ne sont pas des
  `DomainException` : aucune règle métier n'est en jeu. Chaque adaptateur primaire les traduit explicitement :
  `isError` côté MCP, `404` et `503` en *Problem Details* côté API.
- **L'adaptateur ne laisse passer qu'une fiche bornée** : cinq champs, titre et auteur sur une ligne et limités à la
  longueur que le domaine accepte.
- **Un second adaptateur, `FakeBookCatalog`, pour travailler sans réseau**, choisi par `LIBRARY_CATALOG=fake`. Sa
  fiche est calculée depuis l'ISBN, déterministe et visiblement fausse ; un ISBN terminé par `0` est inconnu, par
  `9` il rend le catalogue indisponible. Une valeur inconnue, ou `fake` en production, fait échouer le démarrage.
- **Aucun test ne sort sur le réseau** : le port a sa doublure en mémoire et son trait de contrat, et les requêtes
  HTTP non prévues font échouer la suite.

## Conséquences

- (+) Le profil de chaque tool reste lisible : une écriture en monde fermé, une lecture en monde ouvert.
- (+) Une panne du catalogue n'empêche jamais d'ajouter un livre à la main.
- (+) Le lecteur, ou le modèle, voit la fiche trouvée avant qu'elle ne devienne un livre.
- (−) Deux appels au lieu d'un pour ajouter un livre depuis son ISBN.
- (−) `LookUpBookTool` porte un `catch` que les autres tools n'ont pas : la traduction des `DomainException`, écrite
  une fois dans `LibraryTool` ([ADR-0008](./0008-tools-adaptateurs-minces-refus-metier.md)), ne couvre pas ces deux
  exceptions.
- (−) Le serveur dépend d'un service tiers, sans cache ni nouvelle tentative.

## Alternatives écartées

- **`add_book` qui se complète depuis l'ISBN** : un appel réseau caché dans une écriture, un tool à la fois
  écriture et monde ouvert, et une fiche enregistrée sans avoir été vue.
- **`NotInCatalog` et `CatalogUnavailable` en `DomainException`** : `LibraryTool` et l'API les auraient traduites sans
  une ligne de plus, mais le domaine aurait porté une panne réseau. La règle « exception de domaine = refus métier »
  ([ADR-0008](./0008-tools-adaptateurs-minces-refus-metier.md)) n'aurait plus tenu.
- **Le port qui renvoie `null` jusqu'au tool** : chaque adaptateur primaire aurait écrit son propre message pour un
  ISBN inconnu. Le use case le dit une fois.
- **Un faux catalogue aléatoire** : le même ISBN changerait de fiche d'un appel à l'autre, le tool mentirait sur
  son `idempotentHint` et une démo ne se rejouerait pas.
- **Un faux catalogue fait d'une liste de vrais livres** : il faudrait connaître les ISBN qui répondent, et une
  fiche d'apparence vraie finirait dans une bibliothèque de dev sans qu'on le voie.
- **Renvoyer le JSON du catalogue tel quel** : des dizaines de champs au format libre, écrits par des inconnus, remis
  au modèle.
