# 03 — Premier serveur MCP (stdio)

> Étape 2 de la [roadmap](./roadmap.md). On expose la bibliothèque à un agent avec **deux tools**, `search_books`
> (lecture) et `add_book` (écriture), en transport **stdio**
> ([ADR-0004](./decisions/0004-stdio-avant-http.md)). Les conventions des tools sont actées dans
> [ADR-0008](./decisions/0008-tools-adaptateurs-minces-refus-metier.md).

## Anatomie

```
Claude Code ──(lance)──► docker compose exec -T php php artisan mcp:start library
     ▲                                   │ stdin / stdout : JSON-RPC, une ligne par message
     │                                   ▼
     │                    routes/ai.php : Mcp::local('library', LibraryServer::class)
     │                                   │
     │                    LibraryServer (nom, version, instructions, liste des tools)
     │                                   │ tools/call {name: "add_book", arguments: {…}}
     │                                   ▼
     │                    AddBookTool ── valide la forme ──► AddBookHandler ──► Book ──► BookRepository
     │                                   │                   (Application)    (Domain)   (Eloquent → Postgres)
     └────── résultat du tool ◄──────────┘ BookPresenter : BookView → contenu structuré
```

| Pièce | Où | Rôle |
|---|---|---|
| `LibraryServer` | `app/Library/Infrastructure/Mcp/` | se présente au client (`serverInfo`, `instructions`) et déclare ses tools |
| `LibraryTool` | `…/Mcp/Tools/` | base des tools : traduit un refus métier en erreur de tool |
| `SearchBooksTool`, `AddBookTool` | `…/Mcp/Tools/` | **adaptateurs primaires** : un tool = un use case |
| `ValidatedData` | `…/Infrastructure/` | lit les arguments **déjà validés**, typés ; partagé avec l'API ([09](./09-api-rest.md)) |
| `BookPresenter` | `…/Mcp/` | la représentation d'un livre, la même pour tous les tools |
| `routes/ai.php` | | enregistre le serveur sous le *handle* `library` pour `mcp:start` |

Le domaine et l'application ignorent tout de MCP : PHPArkitect l'interdit (`Laravel\Mcp` banni des couches
intérieures).

## Une session, en vrai

Voici ce qui circule réellement sur stdio (capturé en envoyant du JSON-RPC brut au serveur, puis raccourci).
C'est une session **legacy** (`2025-11-25`), ouverte par `initialize`. Depuis `2026-07-28`, un client peut aussi
appeler directement, sans handshake ([06](./06-transport-http.md)).

**1. Poignée de main.** Le client annonce sa version du protocole ; le serveur répond avec la sienne, ses
*capabilities* et ses **instructions**, un texte que le host peut donner au modèle.

```json
→ {"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"probe","version":"0"}}}
← {"jsonrpc":"2.0","id":1,"result":{"protocolVersion":"2025-11-25",
     "capabilities":{"tools":{"listChanged":false},"resources":{…},"prompts":{…},"completions":{}},
     "serverInfo":{"name":"Marque-page","version":"0.1.0"},
     "instructions":"Marque-page is the user's personal library: … first find its id with `search_books`…"}}
→ {"jsonrpc":"2.0","method":"notifications/initialized"}
```

La dernière ligne est une **notification** : pas d'`id`, donc pas de réponse.

**2. Découverte.** Le modèle ne connaît nos tools que par ce qu'en dit `tools/list` : un nom, une description et
un JSON Schema d'entrée. **C'est tout ce qu'il voit pour décider d'appeler un tool, et avec quoi.**

```json
← {"name":"search_books","title":"Search books",
   "description":"Search the user's library. Every criterion is optional and they combine…",
   "inputSchema":{"type":"object","properties":{
     "query":{"type":"string","description":"Words to look for in the title or the author. Every word must match…"},
     "reading_status":{"type":"string","enum":["to_read","reading","read","abandoned"],…},
     "per_page":{"type":"integer","minimum":1,"maximum":100,"default":20}, …}}}
```

**3. Appel.** Le résultat d'un tool porte un drapeau `isError` et ses données sous **deux formes** :

```json
→ {"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"search_books","arguments":{"isbn":"0-441-17271-7"}}}
← {"jsonrpc":"2.0","id":3,"result":{"isError":false,
     "structuredContent":{"total":1,"page":1,"per_page":20,"books":[{"id":"f7a2…","title":"Dune",
       "author":"Frank Herbert","isbn":"9780441172719","owned":true,"reading_status":"read",…}]},
     "content":[{"type":"text","text":"{\"total\":1,\"page\":1,…}"}]}}
```

- `structuredContent` : un **objet JSON**, que le client peut exploiter sans analyser de texte (révision
  `2025-06-18` de la spec) ;
- `content` : la même donnée **sérialisée en texte**, pour les clients qui ne connaissent pas encore
  `structuredContent`.

C'est ce que produit `Response::structured([...])`, la réponse recommandée par `laravel/mcp` pour des données.
(`Response::json()` existe aussi, mais elle est marquée `@internal` et ne produit que le texte.) L'étape 3
ajoutera l'`outputSchema`, qui décrit à l'avance la forme de ce contenu structuré.

## Trois façons d'échouer

| Appel | Réponse | Qui la produit | Ce que le modèle peut en faire |
|---|---|---|---|
| `add_book` avec un ISBN déjà présent | `result` + `isError: true` : « A book with ISBN 9780441172719 is already in the library. » | le **domaine** (`DuplicateIsbn`), traduit par `LibraryTool` | lire, comprendre, **corriger** (chercher le livre au lieu de l'ajouter) |
| `search_books` avec `"isbn": "0-441-17271-0"` (clé fausse) | `result` + `isError: true` : « "0-441-17271-0" is not a valid ISBN-10 or ISBN-13. » | le **domaine** (`InvalidIsbn`) | corriger l'ISBN, ou chercher par titre |
| `search_books` avec `"per_page": "20"` | `result` + `isError: true` : « The per page field must be an integer. » | la **validation** de forme du tool | corriger ses arguments et réessayer |
| `lend_book`, qui n'existe pas | `error` JSON-RPC `-32602` « Tool [lend_book] not found. » | le **SDK** : le protocole lui-même | rien : c'est un bug du client, pas une situation métier |

La distinction est au cœur de MCP : une erreur **de tool** (`isError`) est un résultat que le modèle lit, une
erreur **JSON-RPC** signale que l'échange lui-même a échoué. On l'approfondira à l'étape 3.

## Pièges rencontrés

- **Le SDK masque les messages en production.** Une exception levée dans un tool devient bien un résultat
  `isError`, mais son message n'est transmis qu'avec `APP_DEBUG` ; sinon, « An internal server error
  occurred. ». Avec `APP_DEBUG`, c'est l'inverse : **n'importe quelle** exception, y compris une erreur SQL,
  part vers le modèle. D'où `LibraryTool`, qui transmet les refus **métier**, et seulement eux
  ([ADR-0008](./decisions/0008-tools-adaptateurs-minces-refus-metier.md)). Un test le vérifie avec
  `app.debug` à `false`.
- **La validation Laravel est laxiste par défaut.** `boolean` accepte `"1"` et `integer` accepte `"20"`. Les
  arguments MCP sont du JSON typé : on exige `boolean:strict` et `integer:strict`, et un modèle qui se trompe de
  type reçoit un message pour se corriger.
- **stdout est sacré.** En stdio, tout ce qui s'écrit sur stdout doit être du JSON-RPC : un `dump()`, un
  warning PHP ou un `echo` casse la session. `-T` dans `docker compose exec` évite qu'un pseudo-terminal
  n'altère le flux.
- **Le helper `assertRegistered()` du SDK instancie les tools sans le conteneur** et échoue sur leurs
  dépendances. Inutile de toute façon : `LibraryServer::tool(…)` passe par un vrai `tools/call`, qui ne trouve
  un tool que s'il est enregistré.

## Un texte, ou un identifiant ?

`search_books` distingue `query` (titre ou auteur, recherche **approximative**) et `isbn` (recherche **exacte**).
Un seul champ pour les deux obligerait à deviner ce que l'utilisateur a tapé : un titre comme « 1984 » ressemble à
un bout d'ISBN, et un ISBN-10 n'est pas une sous-chaîne de l'ISBN-13 stocké. Le critère `isbn` passe par le value
object `Isbn` du domaine : ISBN-10 et ISBN-13 trouvent le même livre, et un ISBN invalide est un refus que le
modèle peut lire.

`query` est découpé en **mots**, et chaque mot doit se trouver dans le titre **ou** l'auteur : « tolkien anneaux »
trouve *Le Seigneur des anneaux*. La casse et les **accents** sont ignorés (extension Postgres `unaccent`) :
« hyperion » trouve *Hypérion*. Pour un humain, rater un accent est une gêne. Pour un modèle, c'est un faux
négatif qu'il ne peut pas détecter : il conclut avec assurance que le livre n'existe pas, et peut même proposer
de l'ajouter une deuxième fois.

**Coût assumé : un parcours complet de la table.** Un motif `%mot%` ne peut utiliser aucun index B-tree, avec ou
sans `unaccent`, et dans tous les moteurs : une collation insensible aux accents (MySQL `utf8mb4_0900_ai_ci`, ou
une collation ICU non déterministe en Postgres 18) simplifie l'écriture, pas le plan d'exécution. Sur une
bibliothèque personnelle de quelques milliers de livres, le parcours coûte moins d'une milliseconde. S'il devait
un jour se voir : une colonne `search_text` sans accents, calculée à la sauvegarde, avec un index GIN `pg_trgm`,
que `ILIKE '%…%'` sait utiliser. On ne peut pas indexer `unaccent()` directement : la fonction est `STABLE`, pas
`IMMUTABLE`.

## Écrire pour un modèle

Les `#[Description]` et les descriptions de paramètres sont l'interface utilisateur du tool. Les règles
appliquées :

- dire **quand** l'utiliser (« to find a book's id before acting on it », « what am I reading? ») ;
- dire ce qu'il **refuse** et quoi faire à la place (« search for it instead of adding it twice ») ;
- interdire l'invention là où un modèle serait tenté (« never invent » un ISBN ou un id) ;
- décrire chaque paramètre non évident, avec ses valeurs (`enum`) et ses bornes (`minimum`, `maximum`).

## Brancher, observer, tester

- **Claude Code** lit `.mcp.json` à la racine du projet : le serveur `library` y est déclaré. La stack doit tourner
  (`make start`) ; au premier lancement, Claude Code demande d'approuver le serveur.
- **MCP Inspector** : `make inspector` (Node requis sur l'hôte) ouvre une interface web qui lance le serveur
  **depuis `.mcp.json`**, exactement comme Claude Code. En ligne de commande :
  `npx @modelcontextprotocol/inspector --cli --config .mcp.json --server library --method tools/list`.
- **Tests** : `tests/Feature/Library/Mcp/`, avec `LibraryServer::tool(AddBookTool::class, [...])`, qui rejoue un
  vrai `tools/call` contre le serveur, base de données comprise. `assertStructuredContent()` vérifie le contenu
  structuré ; sous sa forme `AssertableJson`, il échoue si une clé n'est pas vérifiée (sauf `->etc()`).
- **Le contrat annoncé** : `tools/call` n'appelle jamais `schema()`. Un `enum` cassé ou un `maximum` perdu passe
  donc inaperçu, alors que c'est tout ce que le modèle voit du tool. Chaque tool a un test
  `it_announces_its_contract_to_the_model`, qui compare son entrée de `tools/list` (nom, titre, description,
  `inputSchema`, annotations) **à l'identique**, sous sa forme JSON (`Tests\Support\Library\Announced`).
  Changer une description oblige à changer ce test : c'est voulu, un changement de contrat doit se voir dans le
  diff. `make coverage` a révélé ce trou.
