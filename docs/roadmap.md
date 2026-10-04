# Roadmap

> La feuille de route du projet, **dans l'ordre**. Chaque étape a un **objectif d'apprentissage** explicite :
> on n'ajoute une brique que pour ce qu'elle enseigne. Une étape n'est « faite » que lorsque
> `make quality` est vert et que la doc qui la décrit est à jour.

## Étape 0 — Socle technique

Un commit par brique, `make quality` vert à chacun.

- [x] Roadmap et décisions initiales (ce document, [ADR](./decisions/README.md))
- [x] Projet Laravel vierge
- [x] Stack Docker (nginx → php-fpm 8.5 → postgres 18) et Makefile
- [x] Pint (style)
- [x] PHPStan / Larastan niveau max
- [x] Rector
- [x] `webmozart/assert` (+ extension PHPStan)
- [x] IDE Helper (annotations `@property` des models)
- [x] Infection (mutation testing, MSI 100 % sur le domaine et l'application)
- [x] Outils IA : Laravel Boost (MCP + guidelines) et Pao (sortie agent-optimisée)

> Clin d'œil : le premier serveur MCP du projet n'est pas le nôtre, c'est **celui de Boost**. On l'**utilise**
> avant de **construire** le sien.

## Étape 1 — Le domaine « bibliothèque »

*Apprendre : un serveur MCP n'est qu'un adaptateur ; le métier vit ailleurs
([ADR-0006](./decisions/0006-architecture-hexagonale-ciblee.md)).*

- [x] Documenter le modèle ([01](./01-domaine.md)) et le langage ubiquitaire ([02](./02-langage-ubiquitaire.md))
- [x] PHPArkitect dans la porte qualité : les règles de couches avant la première classe
- [x] Domaine : l'agrégat `Book`, ses value objects et ses invariants
- [x] Application : les use cases (command + handler) et `BookQueries` ; **les tests passent par les use cases**,
  avec le double in-memory, et couvrent le domaine à travers eux
- [x] Infrastructure : migration, repository Eloquent, requêtes de lecture ; même trait de contrat que le
  double
- [x] Seeder de démo réaliste **via les use cases** (possédés / liste d'envies, tous les statuts, des prêts)

## Étape 2 — Premier serveur MCP, transport stdio

*Apprendre : le cycle de vie, `tools/list` / `tools/call`, le JSON Schema d'entrée, l'art de la description.*

- [x] Installer `laravel/mcp`, un serveur `LibraryServer` enregistré en `Mcp::local`
- [x] Un tool en **lecture** (`search_books`) et un en **écriture** (`add_book`) ([03](./03-premier-serveur-mcp.md))
- [x] Brancher le serveur sur Claude Code via `.mcp.json` (`docker compose exec -T …`)
- [x] Observer les échanges avec le **MCP Inspector** (`make inspector`)
- [x] Tests des tools (`LibraryServer::tool(...)->assertOk()`)

## Étape 3 — Les trois primitives

*Apprendre : qui contrôle quoi — modèle, application, utilisateur.*

- [x] Un tool par use case restant, **chacun avec ses quatre annotations**, décidées en concevant le tool
  ([04](./04-les-trois-primitives.md), [ADR-0009](./decisions/0009-quatre-annotations-explicites-par-tool.md)) :
  - [x] les annotations de `search_books` et `add_book`
  - [x] `remove_book`
  - [x] `start_reading`, `finish_reading`, `abandon_reading`
  - [x] `rate_book`
  - [x] `lend_book`, `return_book`
  - [x] `acquire_book`
- [x] **Resources** : URI fixes (les étagères) et **templates d'URI** (`library://books/{id}`)
  ([04](./04-les-trois-primitives.md), [ADR-0011](./decisions/0011-resources-etageres-et-livre.md))
- [x] **Prompts** avec arguments (`recommend_next_read`, `reading_review`)
  ([04](./04-les-trois-primitives.md), [ADR-0012](./decisions/0012-prompts-donnees-embarquees.md))
- [x] L'**`outputSchema`** des réponses structurées, et les **erreurs** métier vs erreurs de protocole
  ([04](./04-les-trois-primitives.md), [ADR-0013](./decisions/0013-output-schema-verifie-par-les-tests.md))

## Étape 4 — Transport Streamable HTTP

*Apprendre : stdio vs HTTP, un protocole devenu sans état (`2026-07-28`), ce qui change quand le serveur est
exposé.*

- [x] Lire la spec `2026-07-28` et documenter le transport ([06](./06-transport-http.md),
  [ADR-0016](./decisions/0016-http-sans-etat-deux-eres-origin-valide.md))
- [ ] Enregistrer le même serveur en `Mcp::web` derrière nginx, publié sur `127.0.0.1` seulement
- [ ] Valider l'en-tête `Origin` (middleware à nous) ; épingler `202`, `405`, `-32020`, `-32022`, `403` par des tests
- [ ] Le brancher sur Claude Code en HTTP, et observer quelle époque il parle
- [ ] Comparer stdio, HTTP legacy et HTTP moderne

## Étape 5 — Authentification

*Apprendre : un serveur MCP distant est une API exposée ; OAuth 2.1 et découverte des métadonnées.*

- [ ] Plusieurs utilisateurs : chacun sa bibliothèque
- [ ] OAuth (Passport) côté serveur, découverte par le client
- [ ] Autorisation par tool

## Étape 6 — Voir le protocole à nu

*Apprendre : ce qui circule réellement, sans SDK.*

> Sa partie stdio est faite **avant l'étape 4** : voir d'abord le protocole nu sur un transport connu rend plus
> visible, ensuite, ce que HTTP y change. Sa partie HTTP suit l'étape 4.

- [x] Un mini-client MCP écrit à la main, sur stdio (`client/`, `make raw-session`) ([05](./05-le-protocole-a-nu.md))
- [x] Journaliser et commenter une session complète (`initialize` → `tools/call` → resources → prompts → fin)
- [ ] Le même client sur HTTP, après l'étape 4, en session **moderne** : `server/discover`, en-têtes, `-32020`, `-32022`

## Plus tard (idées, sans engagement)

- Complétions d'arguments (`completion/complete`)
- Notifications de progression pour un tool long (import d'une liste de livres)
- *MCP Apps* : un tool qui rend une interface (`RendersApp`)
- Un tool qui s'appuie sur une API ouverte (métadonnées ISBN) : `openWorldHint`
