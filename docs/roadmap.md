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
- [ ] `webmozart/assert` (+ extension PHPStan)
- [ ] Outils IA : Laravel Boost (MCP + guidelines) et Pao (sortie agent-optimisée)

> Clin d'œil : le premier serveur MCP du projet n'est pas le nôtre, c'est **celui de Boost**. On l'**utilise**
> avant de **construire** le sien.

## Étape 1 — Le domaine « bibliothèque »

*Apprendre : un serveur MCP n'est qu'un adaptateur ; le métier vit ailleurs.*

- [ ] Documenter le modèle (`Book`, lecture, prêt) et le langage ubiquitaire FR→EN
- [ ] Migrations, modèles, factories, seeder de démo réaliste
- [ ] Les cas d'usage en services applicatifs, testés **sans** MCP

## Étape 2 — Premier serveur MCP, transport stdio

*Apprendre : le cycle de vie, `tools/list` / `tools/call`, le JSON Schema d'entrée, l'art de la description.*

- [ ] Installer `laravel/mcp`, un serveur `Library` enregistré en `Mcp::local`
- [ ] Un tool en **lecture** (`search_books`) et un en **écriture** (`add_book`)
- [ ] Brancher le serveur sur Claude Code via `.mcp.json` (`docker compose exec -T …`)
- [ ] Observer les échanges avec le **MCP Inspector**
- [ ] Tests des tools (`Library::tool(...)->assertOk()`)

## Étape 3 — Les trois primitives

*Apprendre : qui contrôle quoi — modèle, application, utilisateur.*

- [ ] Tools restants : noter, marquer lu, prêter, rendre
- [ ] **Annotations** : `IsReadOnly`, `IsDestructive`, `IsIdempotent`
- [ ] **Resources** : URI fixes et **templates d'URI** (`library://books/{id}`)
- [ ] **Prompts** avec arguments (`recommend-next-read`, bilan de lecture)
- [ ] Réponses **structurées** (`outputSchema`) et **erreurs** métier vs erreurs de protocole

## Étape 4 — Transport Streamable HTTP

*Apprendre : stdio vs HTTP, sessions, ce qui change quand le serveur est partagé.*

- [ ] Enregistrer le même serveur en `Mcp::web` derrière nginx
- [ ] Le brancher sur Claude Code en HTTP
- [ ] Comparer les deux transports (latence, cycle de vie, déploiement)

## Étape 5 — Authentification

*Apprendre : un serveur MCP distant est une API exposée ; OAuth 2.1 et découverte des métadonnées.*

- [ ] Plusieurs utilisateurs : chacun sa bibliothèque
- [ ] OAuth (Passport) côté serveur, découverte par le client
- [ ] Autorisation par tool

## Étape 6 — Voir le protocole à nu

*Apprendre : ce qui circule réellement, sans SDK.*

- [ ] Un mini-client MCP écrit à la main (JSON-RPC brut sur stdio puis HTTP)
- [ ] Journaliser et commenter une session complète (`initialize` → `tools/call`)

## Plus tard (idées, sans engagement)

- Complétions d'arguments (`completion/complete`)
- Notifications de progression pour un tool long (import d'une liste de livres)
- *MCP Apps* : un tool qui rend une interface (`RendersApp`)
- Un tool qui s'appuie sur une API ouverte (métadonnées ISBN) : `openWorldHint`
