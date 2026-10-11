# ADR-0036 — Un refus est un type du projet, `Refusal`

- **Statut** : Accepté (2026-10-11). Complète l'[ADR-0008](./0008-tools-adaptateurs-minces-refus-metier.md) et
  l'[ADR-0034](./0034-catalogue-ouvert-derriere-un-port.md).

## Contexte

Les adaptateurs reconnaissaient un refus à sa classe PHP : tout ce qui hérite de `DomainException`
([ADR-0008](./0008-tools-adaptateurs-minces-refus-metier.md)). Trois symptômes montraient que ce n'était pas le bon
critère :

- `DomainException` est une classe standard, qu'une dépendance peut lever. L'API devait donc vérifier, en plus, que
  le nom de la classe commençait par `App\Library\Domain\`.
- `NotInCatalog` et `CatalogUnavailable` ([ADR-0034](./0034-catalogue-ouvert-derriere-un-port.md)) sont des réponses
  destinées à l'appelant, mais pas des règles du domaine. Elles n'entraient pas dans le moule : il a fallu un `catch`
  dans leur tool, un rendu à part dans l'API et une extension OpenAPI de plus.
- La question « est-ce que ce message peut sortir ? » était posée à une hiérarchie d'exceptions de PHP, qui n'a pas
  été conçue pour y répondre.

## Décision

- **Une interface du projet, `App\Library\Domain\Refusal`**, marque ce que la bibliothèque répond **exprès** : son
  message est écrit pour être lu par celui qui a demandé, un lecteur ou un modèle.
- **Tout refus l'implémente**, quelle que soit sa couche : les quinze refus du domaine, et les deux du catalogue, qui
  restent dans la couche application.
- **Plus aucune classe du projet n'étend `DomainException`** : les refus étendent `RuntimeException`.
- **Les adaptateurs ne reconnaissent que `Refusal`** : `LibraryTool` et `LibraryResource` pour MCP, le rendu des
  *Problem Details* et l'extension OpenAPI pour l'API. Le test sur le préfixe de l'espace de noms disparaît.
- **Toute autre exception est une panne** : son message ne sort pas.
- **Le message reste dans le refus.** Le modèle et l'API ont besoin d'un texte en anglais ; le sortir du cœur
  obligerait à l'écrire dans deux adaptateurs qui ne doivent pas se connaître
  ([ADR-0025](./0025-chaque-adaptateur-possede-son-contrat.md)). Le front, lui, traduit par le type
  ([ADR-0033](./0033-refus-traduits-par-leur-type.md)).

## Conséquences

- (+) Ce qui peut sortir se décide par un type que seul le projet peut donner.
- (+) `LookUpBookTool` redevient un tool comme les autres, sans `catch` ; `CatalogToProblem` et le rendu à part de
  l'API sont supprimés.
- (+) Un nouveau refus, dans n'importe quelle couche intérieure, est traduit par tous les adaptateurs sans une ligne
  de plus, et le test des statuts HTTP le voit.
- (−) `Refusal` vit dans le domaine alors que deux de ses implémentations n'en sont pas : c'est la couche la plus
  intérieure, la seule que toutes les autres ont le droit de connaître.
- (−) Un refus n'est plus une `LogicException` : un code qui attraperait cette classe de PHP ne les verrait plus.
  Aucun ne le faisait.

## Alternatives écartées

- **Garder `DomainException` et le test sur l'espace de noms** : il faudrait l'étendre à chaque couche qui refuse.
- **Une classe abstraite commune** plutôt qu'une interface : elle imposerait un parent unique, alors qu'un marqueur
  suffit.
- **Un refus sans message, fait d'un type et de données**, formaté par chaque adaptateur : le texte que lit le modèle
  serait écrit deux fois, ou partagé par une dépendance entre adaptateurs.
