# 05 — Le protocole à nu

> Étape 6 de la [roadmap](./roadmap.md), faite avant l'étape 4 : voir d'abord ce qui circule sur stdio, sans SDK,
> pour mieux voir ensuite ce que HTTP y change. Un client MCP écrit à la main (`client/`) lance notre serveur, lui
> parle en JSON-RPC brut et affiche tout ce qui passe : `make raw-session`.
> Sa seconde moitié, [sur HTTP en mode moderne](#sur-http-en-mode-moderne), a suivi l'étape 4 :
> `make raw-http-session`.

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

> **Une session legacy.** Ce qui suit est le cycle de vie des versions jusqu'à `2025-11-25`, celui que joue notre
> client. Depuis `2026-07-28`, il n'y a plus de handshake : chaque requête porte sa version dans `_meta`, et notre
> serveur sert aussi ce mode-là ([06](./06-transport-http.md)).

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
`cacheScope`. Ils viennent de la version `2026-07-28`, que le SDK applique même dans une session legacy : `resultType` vaut
`"input_required"` quand le serveur a besoin d'une information du client avant de répondre (pattern MRTR), et
`ttlMs` et `cacheScope` disent combien de temps un client ou un proxy peut garder une liste en cache
([06](./06-transport-http.md)).

## Sur HTTP, en mode moderne

`client/HttpMcpClient.php` parle le protocole **moderne** (`2026-07-28`, voir [06](./06-transport-http.md)), sans
SDK. Il sépare deux choses :

- **le protocole**, qu'il porte lui-même : le corps JSON-RPC et son `_meta`, les en-têtes MCP recopiés du corps, la
  lecture d'une réponse JSON ou d'un flux SSE ;
- **le transport**, qu'on lui passe : une fonction qui reçoit des en-têtes et un corps, et rend un `HttpReply`
  (statut, type de contenu, corps). `HttpMcpClient::over()` la construit avec `curl` pour `make raw-http-session`.
  Les tests passent le **noyau Laravel** : le vrai serveur, sans le réseau, sur la base de test
  (`tests/Feature/RawClient/HttpMcpClientTest.php`).

Ce que le client fait à chaque requête :

- il ajoute à `params._meta` sa version, ses capacités et son identité (`clientInfo`), puisqu'il n'y a plus
  d'`initialize` pour les annoncer une fois pour toutes ;
- il recopie `method` dans `Mcp-Method`, et le nom du tool ou du prompt, ou l'URI de la resource, dans `Mcp-Name` ;
- il encode `Mcp-Name` en `=?base64?…?=` si la valeur n'est pas de l'ASCII sûr (un accent, un espace en bord) ;
- dans un flux SSE, il ignore les notifications (sans `id`) et rend le message qui porte **son** `id`.

Il n'a ni `notify()` ni `close()` : le mode moderne ne définit aucune notification du client sur HTTP, et il n'y a
pas de session à fermer.

### La session, commentée

`make raw-http-session` joue une session en lecture seule, l'équivalent de celle de stdio. Les lignes sont
raccourcies : `→` est la requête (ses en-têtes MCP, puis son corps), `←` la réponse (statut et type, puis corps).

**1. Pas de handshake.** La première requête est déjà une vraie question. `server/discover` est facultatif : le
client l'envoie ici pour connaître le serveur, mais il aurait pu commencer directement par `tools/call`.

```
→ POST MCP-Protocol-Version: 2026-07-28 Mcp-Method: server/discover
  {"jsonrpc":"2.0","id":1,"method":"server/discover","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{},"io.modelcontextprotocol/clientInfo":{"name":"raw-client","version":"0.1.0"}}}}
← 200 application/json
  {"jsonrpc":"2.0","id":1,"result":{"resultType":"complete",…,"supportedVersions":["2026-07-28"],"capabilities":{"tools":{…},"resources":{…},"prompts":{…}},"instructions":"…"}}
```

**2. Un tool.** `Mcp-Name` répète le nom du tool. Le serveur vérifie qu'il correspond au corps : un `200` prouve que
le client a construit ses en-têtes correctement.

```
→ POST MCP-Protocol-Version: 2026-07-28 Mcp-Method: tools/call Mcp-Name: search_books
  {"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"search_books","arguments":{"query":"dune"},"_meta":{…}}}
← 200 application/json
  {"jsonrpc":"2.0","id":3,"result":{"resultType":"complete","content":[…],"structuredContent":{"total":1,…}}}
```

**3. Un refus métier** reste un **résultat** (`isError: true`), donc un `200` : HTTP ne change rien à la distinction
entre erreur de tool et erreur de protocole.

```
→ POST MCP-Protocol-Version: 2026-07-28 Mcp-Method: tools/call Mcp-Name: rate_book
← 200 application/json
  {"jsonrpc":"2.0","id":4,"result":{"resultType":"complete","content":[{"type":"text","text":"A rating goes from 1 to 5, 6 given."}],"isError":true,…}}
```

**4. Resources et prompts.** `Mcp-Name` porte l'URI de la resource, ou le nom du prompt.

```
→ POST MCP-Protocol-Version: 2026-07-28 Mcp-Method: resources/read Mcp-Name: library://shelves/reading
→ POST MCP-Protocol-Version: 2026-07-28 Mcp-Method: prompts/get Mcp-Name: reading_review
```

**5. Les erreurs de protocole prennent un statut HTTP.** Une méthode inconnue : `404`. Une version que le serveur ne
parle pas : `400`, avec la liste de celles qu'il parle. Le client peut alors réessayer avec l'une d'elles.

```
→ POST MCP-Protocol-Version: 2026-07-28 Mcp-Method: books/burn
← 404 application/json
  {"jsonrpc":"2.0","id":7,"error":{"code":-32601,"message":"The method [books\/burn] was not found."}}
→ POST MCP-Protocol-Version: 1900-01-01 Mcp-Method: tools/list
← 400 application/json
  {"jsonrpc":"2.0","id":1,"error":{"code":-32022,"message":"Unsupported protocol version","data":{"supported":["2026-07-28"],"requested":"1900-01-01"}}}
```

**6. Rien à fermer.** Le stdio se termine en fermant l'entrée standard. Ici, il n'y a ni processus ni session : le
client s'arrête, voilà tout.

### Ce que la version à la main a révélé

- **Le base64 de `Mcp-Name` est bien décodé** par le serveur : une URI accentuée, encodée, passe sans `-32020`.
  Mais **`laravel/mcp` accepte aussi l'UTF-8 brut** dans l'en-tête, alors que la spec classe « une valeur d'en-tête
  aux caractères invalides » parmi les échecs de validation. C'est une tolérance du SDK : un client ne doit pas
  compter dessus.
- **Un refus de resource devient un `500`.** Un id de livre invalide sur `library://books/{id}` donne `-32603`
  (erreur interne), comme le notait déjà l'[ADR-0011](./decisions/0011-resources-etageres-et-livre.md). En stdio,
  c'était un simple code mal choisi. En HTTP, le SDK le traduit en `500`, qui dit à tout proxy et à tout client
  « le serveur a planté », alors que c'est la requête qui est fausse. La spec `2026-07-28` donne maintenant
  `-32602` (paramètres invalides) à une resource introuvable, et le SDK produit ce code pour une
  `ValidationException`. Corriger ce point reviendrait à remplacer une conséquence de l'ADR-0011 : c'est une
  décision à part.

## Ce que le SDK faisait pour nous

Écrire le client à la main montre tout ce que `laravel/mcp` (côté serveur) et Claude Code (côté client) prennent en
charge : le découpage en lignes, les `id` et l'association d'une réponse à sa requête, la négociation de version,
la distinction entre requêtes et notifications, et la traduction des exceptions en erreurs JSON-RPC. Rien de tout
cela n'est compliqué, mais c'est ce qui fait qu'un serveur et un client écrits par des gens différents se
comprennent.
