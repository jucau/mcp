# 00 — MCP en bref

## Le problème

Un LLM, seul, ne connaît que ses données d'entraînement et ce qu'on met dans son prompt. Pour qu'il **agisse**
sur un système réel (lire une base, créer un ticket, envoyer un message), chaque application IA devait
écrire sa propre intégration pour chaque système : **N applications × M systèmes** connecteurs.

**MCP** (*Model Context Protocol*, ouvert par Anthropic fin 2024) standardise cette prise : un système écrit
**un serveur MCP**, et **toute** application compatible (Claude Code, Claude Desktop, claude.ai, IDE…) peut
s'y brancher. On passe à **N + M**. D'où l'analogie courante : *« l'USB-C des applications IA »*.

## Les trois rôles

```
┌──────────── Host (Claude Code) ────────────┐
│  LLM  ◄──►  Client MCP ─────────────────────┼──► Serveur MCP « marque-page » ──► Laravel ──► Postgres
│             Client MCP ─────────────────────┼──► Serveur MCP « github »
└─────────────────────────────────────────────┘
```

- **Host** : l'application IA avec laquelle l'utilisateur parle. Elle décide quoi montrer au modèle.
- **Client** : le connecteur, *dans* le host. **Un client par serveur**.
- **Server** : un programme qui **expose des capacités**. **C'est ce qu'on construit ici.**

Le serveur ne parle **jamais** au LLM directement : il répond au client, et le host décide ce qu'il en fait.

## Les trois primitives côté serveur

La distinction clé est **qui décide** de s'en servir.

| Primitive | Contrôlée par | Nature | Exemple Marque-page |
|---|---|---|---|
| **Tool** | le **modèle** | une **action** (avec effets de bord possibles), décrite par un JSON Schema d'entrée | `search_books`, `lend_book` |
| **Resource** | l'**application** | une **donnée** en lecture, adressée par une **URI** | `library://shelves/to-read` |
| **Prompt** | l'**utilisateur** | un **modèle de message** paramétré, souvent proposé en slash-command | `recommend-next-read` |

Conséquence pratique : la **description** d'un tool est un texte que le modèle lit pour décider de l'appeler.
C'est de l'interface utilisateur… pour un LLM. Elle se soigne autant qu'une API publique.

Les tools portent aussi des **annotations** (*hints*) : `readOnlyHint`, `destructiveHint`, `idempotentHint`,
`openWorldHint`. Le host s'en sert pour décider, par exemple, s'il demande confirmation avant d'appeler.

## Le protocole

- **JSON-RPC 2.0** : requêtes (`id` + `method` + `params`), réponses, notifications (sans `id`).
- **Versions datées** : `2024-11-05`, `2025-03-26`, `2025-06-18`, `2025-11-25`, `2026-07-28`.
- **Sans état depuis `2026-07-28`** : chaque requête porte sa version du protocole et les capacités du client dans
  son `_meta`, et le serveur l'accepte ou la refuse seule. `server/discover` permet de connaître le serveur à
  l'avance.
- **Avant (*legacy*, jusqu'à `2025-11-25`)** : une session s'ouvrait par `initialize` (négociation de la version et
  des capabilities), puis `notifications/initialized`, avant les échanges. Un serveur peut encore servir les deux
  époques ([06](./06-transport-http.md)).

## Les deux transports

| Transport | Principe | Quand |
|---|---|---|
| **stdio** | le host **lance** le serveur en sous-processus ; JSON-RPC sur stdin/stdout | local, mono-utilisateur, zéro réseau |
| **Streamable HTTP** | le serveur est un **endpoint HTTP** (POST, réponse JSON ou flux SSE) | distant, partagé, multi-utilisateur — et donc **authentification** (OAuth 2.1) |

## Cas d'usage typiques

1. **Brancher un agent sur son système métier** : base de données, API interne, back-office.
2. **Exposer une recherche** (y compris un RAG) comme tool : l'agent décide **lui-même** quand chercher.
3. **Automatiser des workflows** : tickets, déploiements, observabilité, requêtes en lecture seule.
4. **Outiller les développeurs** : doc versionnée, conventions, schéma de BDD (c'est ce que fait Laravel Boost).

## Ce que MCP n'est pas

- **Pas un framework d'agent** : il ne décide rien, il transporte des capacités.
- **Pas du RAG** : le RAG injecte du contexte *avant* la génération ; un tool MCP est appelé *à la demande du
  modèle*. Les deux se combinent (un tool `search` peut s'appuyer sur un RAG).
- **Pas une API REST de plus** : c'est une API **auto-décrite** (`tools/list`) pensée pour être lue et choisie
  par un modèle.
