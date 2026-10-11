# Marque-page

**Projet pédagogique** d'apprentissage du **Model Context Protocol (MCP)** : un **serveur MCP** construit sur
**Laravel** (`laravel/mcp`), qui permet de piloter une **bibliothèque personnelle** (livres, lectures, prêts)
en langage naturel depuis un agent IA comme Claude Code. La **source de vérité** vit dans
[`docs/`](docs/README.md) : concepts MCP, [roadmap](docs/roadmap.md) et [décisions](docs/decisions/README.md).

## Stack

**nginx** (8090) → **php-fpm 8.5** → **postgres 18**. Laravel 13. Le front React est servi par **Vite** (5173), dans
le conteneur `node`. Tout tourne dans Docker ; le **Makefile** pilote l'ensemble.

## Prérequis

- **Docker** + **Docker Compose**
- **make**, **git**

## Installation

```bash
make install
```

- **App** : <http://localhost:8090>
- **Front** : <http://localhost:5173> (voir [Se connecter au front](#se-connecter-au-front))
- **Postgres** : `localhost:5434` (`marquepage` / `marquepage` / `secret`)

## Commandes utiles

| Commande | Effet |
|----------|-------|
| `make install` | du clone au projet fonctionnel, à jouer une fois |
| `make start` / `make stop` | démarrer / arrêter la stack |
| `make quality` | la porte du back : **ide-helper** → **rector** → **pint** → **openapi** (appliquent) puis **stan** → **ark** → **test** → **coverage** → **infection** |
| `make quality-front` | la porte du front : **openapi** → **front-contract** → **front-format** (appliquent) puis **front-lint** → **front-typecheck** → **front-test** → **front-mutation** |
| `make ci` | ce que jouerait une CI : base de test neuve, les deux portes, puis les données de démo seedées sur la base de test |
| `make pint` | le style de code (Pint), appliqué |
| `make ide-helper` | régénère les annotations `@property` des models |
| `make rector` | les refactorings automatiques (Rector), appliqués |
| `make stan` | l'analyse statique (PHPStan / Larastan niveau max) |
| `make ark` | les règles d'architecture hexagonale (PHPArkitect) |
| `make infection` | le mutation testing (Infection) du domaine et de l'application, MSI 100 % exigé |
| `make openapi` | régénère le contrat de l'API REST (`docs/api/openapi.json`) depuis le code |
| `make front-contract` | régénère les types du client HTTP du front depuis ce contrat |
| `make front-format` | le style du front (Prettier), appliqué |
| `make front-lint` | ESLint sur le front, aucun warning toléré (règles de dépendances du cœur comprises) |
| `make front-typecheck` | la compilation TypeScript stricte du front |
| `make front-test` | les tests du front (Vitest) |
| `make front-mutation` | le mutation testing du front (Stryker), MSI 100 % exigé sur le cœur |
| `make front-install` | réinstalle les dépendances du front (`npm ci`) dans le conteneur `node` |
| `make inspector` | ouvre le MCP Inspector sur le serveur `library` (Node requis sur l'hôte) |
| `make raw-session` | un client MCP écrit à la main (`client/`) joue une session complète et affiche chaque ligne JSON-RPC |
| `make test` | PHPUnit (BDD Postgres dédiée `marquepage_testing`) |
| `make coverage` | la couverture PHPUnit (pcov) : résumé dans le terminal, rapport HTML dans `storage/coverage/index.html` ; échoue sous 100 % des lignes |
| `make test-db` | (re)crée + migre la BDD de test |
| `make migrate` / `make fresh` / `make seed` | migrations / reset / reset + données de démo |
| `make artisan ARGS="…"` | une commande artisan (ex. `make artisan ARGS="migrate:status"`) |
| `make composer ARGS="…"` | une commande composer |
| `make shell` / `make logs` / `make ps` | shell php / logs / état des conteneurs |
| `make build` / `make rebuild` / `make destroy` | (re)construire l'image / tout supprimer, volumes compris |

## Se connecter au front

Ouvrez <http://localhost:5173> (et non le port 8090, qui ne sert que l'API et la page de connexion), puis
connectez-vous avec un compte de démo : `julien@marque-page.test` ou `camille@marque-page.test`, mot de passe
`marque-page`.

Le front démarre avec la stack (`make start`) : le conteneur `node` fait tourner le serveur de dev de Vite. Détails
dans [docs/10](docs/10-frontend.md).

## Utiliser la bibliothèque depuis Claude Code

Le serveur MCP `library` est déclaré dans [`.mcp.json`](.mcp.json) : lancez Claude Code **dans ce dossier**, la
stack démarrée (`make start`), puis approuvez le serveur au premier lancement. Essayez par exemple « qu'est-ce que
je suis en train de lire ? » ou « ajoute Le Sorceleur à ma liste d'envies ».

Pour observer les échanges : `make inspector` ouvre le **MCP Inspector** (Node requis sur l'hôte, pour cet outil
seulement). Détails dans [docs/03](docs/03-premier-serveur-mcp.md).

## Outils IA

- **Laravel Boost** : un serveur MCP pour l'agent (schéma de BDD, tinker, doc Laravel versionnée…) déclaré
  dans `.mcp.json`, plus des guidelines ([`AGENTS.md`](AGENTS.md)) et des skills (`.claude/skills/`). Il
  passe par `docker compose exec` : la stack doit être démarrée (`make start`).
- **Pao** : quand un agent lance `make quality`, la sortie des outils devient une ligne JSON par outil.
