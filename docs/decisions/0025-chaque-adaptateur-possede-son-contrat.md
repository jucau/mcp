# ADR-0025 — Chaque adaptateur possède son contrat : des `JsonResource` pour l'API, et des adaptateurs qui s'ignorent

- **Statut** : Accepté (2026-10-10)

## Contexte

Le serveur MCP et l'API présentent le même `BookView`, avec presque les mêmes champs. Le `BookPresenter` existant
vit en `Infrastructure\Mcp` et décrit aussi l'`outputSchema` des tools
([ADR-0013](./0013-output-schema-verifie-par-les-tests.md), [ADR-0014](./0014-presenters-services-injectes.md)).

Laravel a son propre outil pour présenter une réponse d'API : les **`JsonResource`** (et leurs collections). Elles
emballent la réponse dans `data`, savent paginer un `LengthAwarePaginator` (`meta`, `links`), et sont ce qu'un
développeur Laravel s'attend à trouver.

L'énum `Shelf` (les quatre étagères) vit en `Infrastructure\Mcp`, parce que seules les resources MCP s'en servaient.
L'API en a besoin à son tour. Or l'étagère est un mot du langage ubiquitaire ([02](../02-langage-ubiquitaire.md)) :
une recherche pré-filtrée, sans rapport avec un protocole.

## Décision

- **L'API présente par des `JsonResource`** : `BookResource` pour un livre, sa collection pour une page. Elle ne
  doit rien au `BookPresenter` de MCP.
- **Une page devient un `LengthAwarePaginator` dans l'adaptateur**, pour que la collection produise la pagination
  standard de Laravel. `Application\Page` ne change pas.
- **La `JsonResource` reçoit un `BookView` typé** par son constructeur : la propriété `$resource` du framework est
  `mixed`, et l'accès magique (`$this->title`) échapperait à PHPStan.
- **Le contrat de l'API est un document OpenAPI** versionné, et les tests vérifient que chaque réponse le respecte.
- **Les adaptateurs s'ignorent** : PHPArkitect interdit à `Infrastructure\Api` de dépendre d'`Infrastructure\Mcp`, et
  réciproquement.
- **`Shelf` descend en `Application\Book\Query`** : c'est un concept de lecture du domaine, que les deux adaptateurs
  utilisent.

## Conséquences

- (+) Chaque contrat évolue seul : renommer un champ pour le modèle ne casse pas le frontend, et l'inverse.
- (+) L'API a la forme qu'attend un client Laravel : `data`, `meta`, `links`.
- (+) La règle d'Arkitect rend visible ce que le premier adaptateur avait gardé pour lui : tout concept partagé doit
  descendre dans le cœur, et s'y justifier.
- (−) Onze champs présentés deux fois, de deux façons.
- (−) Le mot *resource* a désormais trois sens dans le projet : la primitive MCP, la ressource OAuth (RFC 8707) et
  la `JsonResource` de Laravel. Le nom de classe et le namespace devront toujours dire de laquelle il s'agit.
- (−) `Application` change, alors que l'étape promettait le contraire : c'est assumé, le changement corrige un
  rangement, il n'ajoute rien.

## Alternatives écartées

- **Réutiliser le `BookPresenter` de MCP** (en le sortant de `Mcp`) : moins de lignes, mais les deux contrats ne
  pourraient plus diverger, et l'`outputSchema` des tools dicterait la forme de l'API.
- **Un presenter maison pour l'API**, sur le modèle de celui de MCP : cohérent avec l'ADR-0014, mais il refait à la
  main ce que `JsonResource` fait déjà (emballage, pagination), et dépayse un développeur Laravel.
- **Dupliquer `Shelf` dans l'API** : deux définitions de « à lire » finiraient par diverger.
- **Pas de contrat OpenAPI** : rien n'empêcherait une réponse de dériver, alors que l'`outputSchema` y veille côté MCP.
