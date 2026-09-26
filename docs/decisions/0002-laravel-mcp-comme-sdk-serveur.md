# ADR-0002 — `laravel/mcp` comme SDK du serveur

- **Statut** : Accepté (2026-09-26)

## Contexte

Un serveur MCP peut s'écrire dans n'importe quel langage : il suffit de parler JSON-RPC sur stdio ou HTTP.
Les SDK officiels de référence sont en TypeScript et en Python. Côté PHP, Laravel publie
**`laravel/mcp`** : serveurs, tools, resources, prompts, transports stdio et HTTP, OAuth via Passport,
helpers de test, et une commande pour lancer le **MCP Inspector**.

Les deux autres projets pédagogiques (`ddd/`, `rag/`) sont en Laravel, avec la même chaîne qualité.

## Décision

Le serveur est une application **Laravel 13** qui utilise **`laravel/mcp`**. L'outillage (Docker, Makefile,
Pint, PHPStan niveau max, Rector, `webmozart/assert`, Boost, Pao) reprend celui de `ddd/`.

## Conséquences

- (+) On apprend **un** sujet nouveau (MCP) sur une base maîtrisée, au lieu de deux en même temps.
- (+) Le SDK se lit dans `vendor/` : on peut voir comment `tools/call` est routé, sérialisé, validé.
- (+) Tests des primitives sans client réel (`Server::tool(...)->assertOk()`).
- (−) Le SDK masque le JSON-RPC. D'où l'étape 6 de la [roadmap](../roadmap.md) : un client écrit à la main
  pour voir le protocole à nu.
- (−) Écosystème et exemples moins fournis qu'en TypeScript ou Python.

## Alternatives écartées

- **SDK TypeScript** : la référence, mais une stack de plus à apprendre en parallèle du protocole.
- **SDK Python (FastMCP)** : très concis, mêmes réserves.
- **JSON-RPC à la main dès le départ** : formateur, mais on passerait l'essentiel du temps sur la
  plomberie (négociation de version, pagination, SSE) au lieu des concepts. Gardé pour l'étape 6.
