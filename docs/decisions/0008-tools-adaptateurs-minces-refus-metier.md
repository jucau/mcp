# ADR-0008 — Les tools sont des adaptateurs minces qui transmettent les refus métier

- **Statut** : Accepté (2026-09-26). Complété par l'[ADR-0036](./0036-un-refus-est-un-type-du-projet.md) : un refus
  se reconnaît désormais à l'interface `Refusal`, plus à `DomainException`.

## Contexte

Un tool MCP est un adaptateur primaire ([ADR-0006](./0006-architecture-hexagonale-ciblee.md)). Trois questions
se posent dès le premier tool :

1. **Ce qui entre** : les arguments viennent d'un modèle, qui peut se tromper de forme (une chaîne pour un
   entier, une valeur hors `enum`).
2. **Ce qui échoue** : le domaine lève des exceptions dont le message est écrit pour être lu par le modèle.
   Or `laravel/mcp` ne transmet le message d'une exception qu'avec `APP_DEBUG` ; en production, il le remplace
   par « An internal server error occurred. ». Et avec `APP_DEBUG`, il transmet **tout**, y compris une erreur
   SQL.
3. **Ce qui sort** : plusieurs tools renverront un livre ; le modèle doit toujours le voir sous la même forme.

## Décision

- **Un tool = un use case.** Le tool valide la **forme** des arguments, appelle **un** handler, présente le
  résultat. Aucune règle métier dans un tool.
- **Validation stricte de la forme** au bord : `$request->validate()` avec `boolean:strict` et `integer:strict`.
  Une erreur de forme revient au modèle en `isError`, avec le nom du champ. Le **sens** reste validé par le
  domaine.
- **Tous les tools étendent `LibraryTool`**, dont le `handle()` final traduit toute `DomainException` en
  `Response::error($message)`. Toute autre exception est laissée au SDK : masquée en production, journalisée.
- **Des données structurées** : les tools répondent par `Response::structured()` (contenu structuré + son texte
  JSON), la réponse recommandée par `laravel/mcp` ; jamais `Response::json()`, interne au SDK.
- **Un seul format de sortie par concept** : `BookPresenter` pour un livre, clés en `snake_case`.
- **Noms de tools en `snake_case`**, verbe + objet (`search_books`, `add_book`), fixés par `#[Name]` plutôt que
  dérivés du nom de classe.

## Conséquences

- (+) Le modèle reçoit les refus métier **en production comme en dev**, et jamais les détails d'une panne.
- (+) La traduction erreur métier → erreur de tool est écrite **une fois**, pour tous les tools à venir.
- (+) Les tools restent triviaux : leurs tests Feature vérifient le câblage, les use cases portent le métier.
- (−) Les dépendances d'un tool passent par son constructeur (`handle()` est pris par la base), ce qui rend le
  helper `assertRegistered()` du SDK inutilisable : il instancie les tools sans le conteneur.
- (−) Une `DomainException` levée par erreur dans une couche technique serait transmise au modèle : la
  discipline « exception de domaine = refus métier » doit tenir.

## Alternatives écartées

- **Laisser le SDK gérer les exceptions** : messages métier perdus en production, fuites en dev.
- **Un `try`/`catch` dans chaque tool** : neuf fois le même code, et un oubli suffit.
- **Mettre `APP_DEBUG` à `true` en production** : ferait fuiter n'importe quelle erreur vers le modèle.
