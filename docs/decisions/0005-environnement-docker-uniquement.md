# ADR-0005 — Environnement 100 % Docker, piloté par un Makefile

- **Statut** : Accepté (2026-09-26)

## Contexte

Le projet doit se lancer pareil sur n'importe quelle machine, sans installer PHP, Composer ni Postgres
localement, et cohabiter avec `ddd/` et `rag/` qui tournent parfois en même temps.

Il a une contrainte propre à MCP : en **stdio**, c'est le **host** (Claude Code) qui lance le serveur. La
commande qu'il exécute doit donc atteindre le PHP du projet, avec sa base de données.

## Décision

- Stack **nginx → php-fpm 8.5 → postgres 18**, identique à `ddd/`, sur des ports dédiés (**8090**, **5434**)
  pour ne pas entrer en collision avec les autres projets.
- **Tout passe par le conteneur `php`** (composer, artisan, outils qualité), via le **Makefile**.
- Les tests tournent sur une **BDD Postgres dédiée** (`marquepage_testing`), forcée par `phpunit.xml`.
- En stdio, le host lancera le serveur par `docker compose exec -T php php artisan mcp:start …`.

## Conséquences

- (+) `git clone` + `make` suffisent ; mêmes versions pour tout le monde.
- (+) Tests sur le même moteur qu'en dev : pas de surprise SQLite ↔ Postgres.
- (+) nginx est déjà là pour l'étape 4 (Streamable HTTP) : le serveur MCP web sera une route comme une autre.
- (−) Le serveur stdio suppose la stack **démarrée** : si `make start` n'a pas été lancé, Claude Code ne
  pourra pas s'y connecter.
- (−) `-T` est obligatoire dans l'`exec` : sans lui, Docker alloue un pseudo-terminal qui altère le flux
  JSON-RPC.

## Alternatives écartées

- **PHP local (Herd, php.new)** : plus rapide à démarrer, mais un environnement de plus à maintenir et des
  versions qui divergent d'un projet à l'autre.
- **SQLite** : zéro service, mais on testerait sur un autre moteur qu'en dev, et on s'écarterait de `ddd/`.
- **Laravel Sail** : même idée, mais on garde la stack et le Makefile déjà maîtrisés dans `ddd/`.
