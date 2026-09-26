# Marque-page

**Projet pédagogique** d'apprentissage du **Model Context Protocol (MCP)** : un **serveur MCP** construit sur
**Laravel** (`laravel/mcp`), qui permet de piloter une **bibliothèque personnelle** (livres, lectures, prêts)
en langage naturel depuis un agent IA comme Claude Code. La **source de vérité** vit dans
[`docs/`](docs/README.md) : concepts MCP, [roadmap](docs/roadmap.md) et [décisions](docs/decisions/README.md).

## Stack

**nginx** (8090) → **php-fpm 8.5** → **postgres 18**. Laravel 13. Tout tourne dans Docker ; le **Makefile**
pilote l'ensemble.

## Prérequis

- **Docker** + **Docker Compose**
- **make**, **git**

Inutile d'avoir PHP, Composer ou Postgres installés en local : tout vit dans les conteneurs.

## Installation

```bash
git clone <repo> marque-page && cd marque-page

cp .env.example .env
cp .env.testing.example .env.testing

make start                            # build + démarre nginx / php / postgres
make composer ARGS="install"          # dépendances PHP (dans le conteneur)
make artisan ARGS="key:generate"
make artisan ARGS="key:generate --env=testing"

make migrate                          # BDD de dev
make test-db                          # crée + migre la BDD de test dédiée
```

- **App** : <http://localhost:8090>
- **Postgres** : `localhost:5434` (`marquepage` / `marquepage` / `secret`)

## Commandes utiles

| Commande | Effet |
|----------|-------|
| `make start` / `make stop` | démarrer / arrêter la stack |
| `make quality` | la porte unique, verte avant chaque commit : **ide-helper** → **rector** → **pint** (appliquent) puis **stan** → **ark** → **test** → **infection** |
| `make pint` | le style de code (Pint), appliqué |
| `make ide-helper` | régénère les annotations `@property` des models |
| `make rector` | les refactorings automatiques (Rector), appliqués |
| `make stan` | l'analyse statique (PHPStan / Larastan niveau max) |
| `make ark` | les règles d'architecture hexagonale (PHPArkitect) |
| `make infection` | le mutation testing (Infection) du domaine et de l'application, MSI 100 % exigé |
| `make test` | PHPUnit (BDD Postgres dédiée `marquepage_testing`) |
| `make test-db` | (re)crée + migre la BDD de test |
| `make migrate` / `make fresh` / `make seed` | migrations / reset / reset + données de démo |
| `make artisan ARGS="…"` | une commande artisan (ex. `make artisan ARGS="migrate:status"`) |
| `make composer ARGS="…"` | une commande composer |
| `make shell` / `make logs` / `make ps` | shell php / logs / état des conteneurs |
| `make build` / `make rebuild` / `make destroy` | (re)construire l'image / tout supprimer, volumes compris |

## Outils IA

- **Laravel Boost** : un serveur MCP pour l'agent (schéma de BDD, tinker, doc Laravel versionnée…) déclaré
  dans `.mcp.json`, plus des guidelines ([`AGENTS.md`](AGENTS.md)) et des skills (`.claude/skills/`). Il
  passe par `docker compose exec` : la stack doit être démarrée (`make start`).
- **Pao** : quand un agent lance `make quality`, la sortie des outils devient une ligne JSON par outil.
