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
- [x] Enregistrer le même serveur en `Mcp::web` derrière nginx, publié sur `127.0.0.1` seulement
- [x] Valider l'en-tête `Origin` (middleware à nous) ; épingler `202`, `405`, `-32020`, `-32022`, `403` par des tests
- [x] Le brancher sur Claude Code en HTTP, et observer quelle époque il parle (moderne : `server/discover`, aucun `initialize`)
- [x] Comparer stdio, HTTP legacy et HTTP moderne, mesures à l'appui (`make benchmark`)

## Étape 5 — Authentification

*Apprendre : un serveur MCP distant est une API exposée ; OAuth 2.1 et découverte des métadonnées.*

- [x] **Le domaine multi-lecteurs**, sans aucun protocole : chaque livre appartient à un lecteur (`ReaderId`),
  les ports ne voient que les livres du lecteur qui demande, l'ISBN est unique par lecteur ; seeder à plusieurs
  lecteurs ([01](./01-domaine.md), [ADR-0018](./decisions/0018-un-lecteur-par-livre-sans-compte-dans-le-domaine.md))
- [x] **OAuth 2.1 sur HTTP** (Passport) : métadonnées de la ressource et du serveur d'autorisation, `401` et
  découverte, le compte traduit en lecteur ; en stdio, le lecteur vient de l'environnement ; le fuseau devient une
  donnée du compte ([07](./07-authentification.md), [ADR-0019](./decisions/0019-comptes-en-infrastructure-lecteur-ferme-par-defaut.md),
  [ADR-0020](./decisions/0020-passport-serveur-d-autorisation-destinataire-implicite.md))

## Étape 6 — Voir le protocole à nu

*Apprendre : ce qui circule réellement, sans SDK.*

> Sa partie stdio est faite **avant l'étape 4** : voir d'abord le protocole nu sur un transport connu rend plus
> visible, ensuite, ce que HTTP y change. Sa partie HTTP suit l'étape 4.

- [x] Un mini-client MCP écrit à la main, sur stdio (`client/`, `make raw-session`) ([05](./05-le-protocole-a-nu.md))
- [x] Journaliser et commenter une session complète (`initialize` → `tools/call` → resources → prompts → fin)
- [x] Le même client sur HTTP, après l'étape 4, en session **moderne** : `server/discover`, en-têtes, `-32022`
  (`client/HttpMcpClient.php`, `make raw-http-session`)

## Étape 7 — Entretenir, puis les allers-retours

*Apprendre : le mécanisme phare de la spec `2026-07-28`, quand le serveur a besoin de l'utilisateur au milieu d'un
appel.*

- [x] Mettre à jour les dépendances (Laravel, Boost, Infection, `json-schema` ; PHPUnit 13 à part, majeure), et
  faire échouer la suite sur le moindre warning, notice ou dépréciation (`failOnAllIssues`)
- [ ] **Les requêtes en plusieurs allers-retours (MRTR)** : `remove_book` demande confirmation avant de supprimer
  (*elicitation*). Le serveur répond `input_required`, le client rejoue la requête avec la réponse.
  **En attente** : `laravel/mcp` v1.0.1 ne gère ni `input_required` ni `inputResponses` ; le support
  (`$request->ask()`, [laravel/mcp#363](https://github.com/laravel/mcp/pull/363)) a été mergé puis reverté, et
  n'est pas publié. On reprend à sa release plutôt que de remplacer `tools/call` dans le SDK

## Étape 8 — Les complétions d'arguments

*Apprendre : une primitive qui ne sert que l'humain, et ce qu'elle peut révéler.*

- [x] Documenter `completion/complete` ([08](./08-completions.md),
  [ADR-0023](./decisions/0023-completer-seulement-ce-qu-un-humain-choisit.md))
- [x] Annoncer la capability `completions`, et compléter `reading_review.year` par les années de lecture du
  lecteur ; l'`id` et `mood` restent sans complétion

## Étape 9 — Un second adaptateur : l'API REST

*Apprendre : ce que l'hexagone promet face à un second adaptateur primaire, ce que MCP emprunte à HTTP et à OAuth, et
à qui est destiné un token quand deux serveurs partagent un serveur d'autorisation.*

- [x] Documenter l'API et le destinataire des tokens ([09](./09-api-rest.md),
  [ADR-0024](./decisions/0024-api-rest-une-url-par-use-case.md), [ADR-0025](./decisions/0025-chaque-adaptateur-possede-son-contrat.md),
  [ADR-0026](./decisions/0026-un-destinataire-explicite-par-token.md))
- [x] **Le destinataire des tokens**, avant toute route : `resource` validé à l'autorisation et affiché sur l'écran
  d'accord, enregistré avec le token, vérifié par `/mcp` ; un token sans destinataire n'ouvre rien ; les URL
  annoncées viennent d'`APP_URL` ([ADR-0027](./decisions/0027-urls-annoncees-depuis-app-url.md))
- [x] Les adaptateurs s'ignorent (PHPArkitect), et `Shelf` descend en `Application`
- [x] Les lectures : `GET /api/books`, `GET /api/books/{id}`, `GET /api/shelves/{shelf}`, le lecteur authentifié
  choisi par le groupe `/api`, les métadonnées de la ressource et le `401`
- [x] Les écritures : `POST /api/books`, `DELETE /api/books/{id}` et les sept actions
- [x] Les refus en *Problem Details*, et le test qui garantit un statut à chaque refus du domaine
- [x] Le contrat OpenAPI, généré depuis le code et vérifié par chaque test de l'API
  ([ADR-0028](./decisions/0028-openapi-genere-par-scramble-verifie-par-spectator.md))
- [x] Pour de vrai : Claude Code refait son parcours OAuth, et son nouveau token porte le destinataire `/mcp`
- [x] Pour de vrai, côté API : un client OAuth suit la découverte jusqu'à `/api` (le frontend, [10](./10-frontend.md))

## Étape 10 — Le frontend

*Apprendre : le parcours OAuth vu d'un client public, ce qu'est le cœur d'un client quand les règles vivent sur le
serveur, et un client typé par le contrat de l'API.*

- [x] Documenter le frontend ([10](./10-frontend.md), [ADR-0029](./decisions/0029-hexagone-front-sans-domaine-duplique.md),
  [ADR-0030](./decisions/0030-spa-cliente-oauth-publique.md), [ADR-0031](./decisions/0031-tenter-puis-afficher-le-refus.md),
  [ADR-0032](./decisions/0032-client-http-type-par-le-contrat.md))
- [x] Le socle : un service `node`, Vite + React + TypeScript strict, ESLint, Prettier, les couches vérifiées, Vitest,
  Stryker, les types du contrat ; **le front dans `make quality`**
- [x] La connexion : le client OAuth pré-enregistré, CORS, la découverte depuis le `401`, PKCE et `resource`, le token
  en mémoire
- [x] La bibliothèque : la recherche, les filtres, la pagination, les étagères
- [x] La fiche d'un livre et ses actions, les refus du domaine traduits par leur type
- [x] L'ajout d'un livre
- [ ] Le bout en bout (Playwright), et pour de vrai dans un navigateur : la case laissée ouverte à l'étape 9

## Plus tard (idées, sans engagement)

- Notifications de progression pour un tool long (import d'une liste de livres)
- *MCP Apps* : un tool qui rend une interface (`RendersApp`)
- Un tool qui s'appuie sur une API ouverte (métadonnées ISBN) : `openWorldHint`
- Des **scopes par tool** et le *step-up* (`403 insufficient_scope`) : `laravel/mcp` n'en gère qu'un (`mcp:use`),
  il faudra déclarer et annoncer les nôtres ([07](./07-authentification.md))
- Les ***Client ID Metadata Documents*** côté serveur d'autorisation : Claude Code s'en passe aujourd'hui (il a
  utilisé l'enregistrement dynamique), mais la spec déprécie ce dernier
