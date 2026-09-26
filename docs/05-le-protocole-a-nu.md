# 05 — Le protocole à nu

> Étape 6 de la [roadmap](./roadmap.md), faite avant l'étape 4 : voir d'abord ce qui circule sur stdio, sans SDK,
> pour mieux voir ensuite ce que HTTP y change. Un client MCP écrit à la main (`client/`) lance notre serveur, lui
> parle en JSON-RPC brut et affiche tout ce qui passe : `make raw-session`.

## Ce qu'est MCP, vu du fil

MCP n'est qu'une convention de messages **JSON-RPC 2.0**. Sur stdio, le client lance le serveur comme un
sous-processus, puis :

- il **écrit** ses messages sur l'entrée standard du serveur, **un message JSON par ligne** : un message ne doit donc
  contenir aucun retour à la ligne brut (ceux d'un texte sont échappés en `\n`) ;
- il **lit** les messages du serveur sur sa sortie standard, une ligne à la fois ;
- la sortie d'erreur du serveur est réservée aux logs : le client peut l'afficher, jamais la lire comme un message.

C'est pourquoi un simple `echo` ou un warning PHP sur la sortie standard casse une session : ça ne produit pas une
ligne JSON-RPC valide ([03](./03-premier-serveur-mcp.md)).

## Trois sortes de messages

| Message | A un `id` ? | Attend une réponse ? | Exemple |
|---|---|---|---|
| **requête** | oui | oui, avec le **même `id`** | `{"jsonrpc":"2.0","id":3,"method":"tools/list"}` |
| **réponse** | oui, celui de la requête | — | `{"jsonrpc":"2.0","id":3,"result":{…}}` ou `…"error":{…}` |
| **notification** | **non** | **jamais** | `{"jsonrpc":"2.0","method":"notifications/initialized"}` |

L'`id` est la seule chose qui relie une réponse à sa requête. Le client choisit ses `id` (ici 1, 2, 3…). Il peut
recevoir, entre deux réponses, des notifications du serveur, et doit les distinguer des réponses.

Une réponse contient **soit** `result`, **soit** `error` (`code`, `message`), jamais les deux. Les codes d'erreur
standards de JSON-RPC : `-32700` (JSON illisible), `-32600` (requête invalide), `-32601` (méthode inconnue),
`-32602` (paramètres invalides), `-32603` (erreur interne).

## Le cycle de vie

1. **`initialize`** (requête) : le client annonce la version du protocole qu'il parle, ses capacités et son nom. Le
   serveur répond avec **sa** version, ses capacités (`tools`, `resources`, `prompts`…), son nom et ses
   `instructions`.
2. **`notifications/initialized`** (notification) : le client confirme. La session est ouverte.
3. **Les échanges** : `tools/list`, `tools/call`, `resources/read`, `prompts/get`… et `ping` à tout moment.
4. **La fin** : sur stdio, il n'y a pas de message de fin. Le client **ferme l'entrée standard** du serveur, qui se
   termine.

**La négociation de version.** Le client propose une version. Si le serveur la connaît, il répond avec la même.
Sinon, il répond avec la sienne, et c'est au client de décider s'il continue ou s'il se déconnecte. Observé :
avec une proposition `2099-01-01`, notre serveur répond `2025-11-25`.

**Ce que le serveur tolère.** La spec dit que le client ne doit rien envoyer d'autre que `ping` avant
`notifications/initialized`. Notre serveur (`laravel/mcp`) n'y veille pas : il répond quand même. Et pour un JSON
illisible, il répond `-32700` sans champ `id`, alors que JSON-RPC exige `"id": null`. Ce sont deux écarts sans
conséquence, mais un client strict ne devrait compter sur aucune de ces tolérances.

## Le client écrit à la main

`client/McpClient.php` fait une centaine de lignes, en PHP pur, sans `laravel/mcp` :

- **`launch()`** démarre le serveur avec `proc_open` : son entrée et sa sortie standards deviennent deux tubes, et
  sa sortie d'erreur reste celle du terminal ;
- **`request()`** attribue le prochain `id`, écrit la ligne, puis lit des lignes **jusqu'à celle qui porte ce même
  `id`**. Les autres messages, comme les notifications du serveur, sont affichés et ignorés ;
- **`notify()`** écrit une ligne sans `id`, et n'attend rien ;
- **`close()`** ferme les tubes et renvoie le code de sortie du serveur.

Un détail que le SDK nous cachait : sans paramètres, `params` doit être **absent**, et non `[]`. En JSON, un
tableau PHP vide devient `[]` et non `{}`. Pour `capabilities`, qui doit être un objet vide, le client envoie
donc un `stdClass`.

Le test `tests/Feature/RawClient/McpClientTest.php` fait dialoguer ce client avec le vrai serveur.

## Une session complète, commentée

`make raw-session` joue la session suivante, en lecture seule. Les lignes sont raccourcies (`…`) : la sortie
réelle les donne en entier.

**1. L'ouverture.** Le client propose `2025-11-25`, le serveur accepte la même version, annonce ses trois
capacités et donne ses `instructions`, que le host transmet au modèle.

```
→ {"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"raw-client","version":"0.1.0"}}}
← {"jsonrpc":"2.0","id":1,"result":{"protocolVersion":"2025-11-25","capabilities":{"tools":{"listChanged":false},"resources":{"listChanged":false},"prompts":{"listChanged":false}},"serverInfo":{"name":"Marque-page","version":"0.1.0"},"instructions":"Marque-page is the user's personal library: …"}}
→ {"jsonrpc":"2.0","method":"notifications/initialized"}
```

La notification n'a pas d'`id`, et aucune réponse ne suit. `listChanged: false` : le serveur ne préviendra pas si
ses listes changent en cours de session.

**2. `ping`** : une requête sans paramètres, un résultat vide. Il sert à vérifier que l'autre côté répond.

```
→ {"jsonrpc":"2.0","id":2,"method":"ping"}
← {"jsonrpc":"2.0","id":2,"result":{…}}
```

**3. Un tool.** `tools/list` renvoie les dix tools avec leurs schémas. `tools/call` renvoie le résultat **deux
fois** : en texte (`content`), pour les clients qui ne lisent que ça, et en objet (`structuredContent`), conforme à
l'`outputSchema`.

```
→ {"jsonrpc":"2.0","id":4,"method":"tools/call","params":{"name":"search_books","arguments":{"query":"dune"}}}
← {"jsonrpc":"2.0","id":4,"result":{"content":[{"type":"text","text":"{\"total\":1,…}"}],"structuredContent":{"total":1,"page":1,"per_page":20,"books":[{"id":"f7a2ea30-…","title":"Dune",…}]}}}
```

**4. Un refus métier.** Le client réutilise l'id trouvé à l'étape précédente, comme le ferait un modèle. Le refus
est un **résultat** (`result`, `isError: true`), pas une erreur JSON-RPC : le modèle le lit et peut corriger.

```
→ {"jsonrpc":"2.0","id":5,"method":"tools/call","params":{"name":"rate_book","arguments":{"book_id":"f7a2ea30-…","rating":6}}}
← {"jsonrpc":"2.0","id":5,"result":{"content":[{"type":"text","text":"A rating goes from 1 to 5, 6 given."}],"isError":true}}
```

**5. Les resources.** `resources/list` donne les URI fixes, `resources/templates/list` les templates, et
`resources/read` le contenu, avec son `mimeType`.

```
→ {"jsonrpc":"2.0","id":8,"method":"resources/read","params":{"uri":"library://shelves/reading"}}
← {"jsonrpc":"2.0","id":8,"result":{"contents":[{"text":"{\"books\":[…]}","uri":"library://shelves/reading","mimeType":"application/json"}]}}
```

**6. Les prompts.** `prompts/get` renvoie des **messages**, prêts à être insérés dans la conversation.

```
→ {"jsonrpc":"2.0","id":10,"method":"prompts/get","params":{"name":"reading_review","arguments":{"year":"2025"}}}
← {"jsonrpc":"2.0","id":10,"result":{"description":"Review the user's reading over a year: …","messages":[{"role":"user","content":{"type":"text","text":"Review my reading year 2025: …"}}]}}
```

**7. Une méthode inconnue** : une **erreur** JSON-RPC (`error`), et aucun `result`.

```
→ {"jsonrpc":"2.0","id":11,"method":"books/burn"}
← {"jsonrpc":"2.0","id":11,"error":{"code":-32601,"message":"The method [books\/burn] was not found."}}
```

**8. La fin.** Le client ferme l'entrée standard, et le serveur se termine avec le code `0`.

**Ce que la transcription montre en plus**, sans que nous l'ayons demandé : chaque résultat porte un
`"resultType":"complete"` et un `_meta` `io.modelcontextprotocol/serverInfo`, et les listes portent `ttlMs` et
`cacheScope`. Ils viennent du SDK et de la version `2025-11-25`. Nous ne nous en servons pas, et je n'ai pas vérifié
leur rôle exact dans la spec : ils sont signalés ici comme observés, pas expliqués.

## Ce que le SDK faisait pour nous

Écrire le client à la main montre tout ce que `laravel/mcp` (côté serveur) et Claude Code (côté client) prennent en
charge : le découpage en lignes, les `id` et l'association d'une réponse à sa requête, la négociation de version,
la distinction entre requêtes et notifications, et la traduction des exceptions en erreurs JSON-RPC. Rien de tout
cela n'est compliqué, mais c'est ce qui fait qu'un serveur et un client écrits par des gens différents se
comprennent.
