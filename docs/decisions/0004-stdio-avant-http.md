# ADR-0004 — Transport stdio d'abord, Streamable HTTP ensuite

- **Statut** : Accepté (2026-09-26)

## Contexte

MCP définit deux transports : **stdio** (le host lance le serveur en sous-processus) et **Streamable HTTP**
(le serveur est un endpoint web). Le second implique sessions, déploiement et, dès qu'il est partagé,
authentification OAuth.

## Décision

On commence en **stdio** (`Mcp::local`), lancé par Claude Code via `docker compose exec -T php php artisan
mcp:start …`. On passe en **Streamable HTTP** (`Mcp::web`) à l'étape 4, puis on ajoute l'**authentification**
à l'étape 5. Les tools, resources et prompts sont **les mêmes** dans les deux cas : seul l'enregistrement
change.

## Conséquences

- (+) Une variable à la fois : on apprend les primitives sans se battre avec le réseau ou l'auth.
- (+) Montre par l'exemple que le transport est **orthogonal** aux capacités exposées.
- (−) stdio impose une discipline : **rien d'autre que du JSON-RPC sur stdout** (un `dump()` ou un warning PHP
  casse la session). Piège formateur, à documenter quand on le rencontre.
- (−) En stdio, un seul utilisateur : la question « à qui est cette bibliothèque ? » est repoussée à l'étape 5.

## Alternatives écartées

- **HTTP d'emblée** : plus proche d'un déploiement réel, mais mélange trois apprentissages (primitives,
  transport, auth) dans la même étape.
