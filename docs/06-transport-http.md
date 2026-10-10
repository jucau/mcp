# 06 — Le transport Streamable HTTP

> Étape 4 de la [roadmap](./roadmap.md). Le même `LibraryServer` est servi une seconde fois, cette fois comme
> endpoint HTTP derrière nginx. Les tools, les resources et les prompts ne changent pas : seul l'enregistrement
> change ([ADR-0004](./decisions/0004-stdio-avant-http.md)). Les choix de cette étape sont actés dans
> [ADR-0016](./decisions/0016-http-sans-etat-deux-eres-origin-valide.md).

## Deux époques du protocole

On ne peut pas parler du transport HTTP sans voir d'abord ce que la version **`2026-07-28`** de la spec a changé.
**MCP est devenu sans état, sur tous les transports.** La spec distingue maintenant deux époques :

| | **Legacy** (jusqu'à `2025-11-25`) | **Moderne** (`2026-07-28` et après) |
|---|---|---|
| Ouverture | handshake `initialize` → `notifications/initialized` | aucune : chaque requête se suffit à elle-même |
| Version du protocole | négociée **une fois**, pour la session | portée par **chaque requête**, dans `_meta` |
| Capacités du client | annoncées une fois à l'`initialize` | portées par chaque requête, dans `_meta` |
| Connaître le serveur à l'avance | la réponse à `initialize` | `server/discover`, que le serveur **doit** implémenter, que le client **peut** appeler |
| Version refusée | le serveur propose la sienne, le client décide | erreur `-32022` qui liste les versions supportées, le client réessaie |
| Session HTTP | `Mcp-Session-Id`, que le serveur **peut** attribuer, fermée par `DELETE` | **supprimée** |
| Le serveur interroge le client (sampling, elicitation…) | il lui envoie ses propres requêtes | il répond `input_required`, le client **rejoue** sa requête avec les réponses (pattern MRTR) |
| `ping`, `logging/setLevel` | présents | supprimés |

Une requête moderne porte donc ses métadonnées dans `params._meta` :

```json
{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"search_books","arguments":{"query":"dune"},
 "_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{}}}}
```

**Pourquoi ce changement.** Avec une session, un serveur HTTP doit garder un état par client. Il faut alors que
toutes les requêtes d'un client arrivent sur la même instance, ou que l'état soit partagé entre instances. Sans
session, n'importe quelle instance peut répondre à n'importe quelle requête, comme une API HTTP classique : on
peut mettre plusieurs instances derrière un load balancer sans rien de plus. Un serveur qui a vraiment besoin
d'un état d'un appel à l'autre le gère lui-même, avec des identifiants qu'il émet et que le client repasse comme
arguments ordinaires.

**Notre serveur parle les deux époques.** `laravel/mcp` répond à `initialize` en `2025-11-25` (c'est la session
de [05](./05-le-protocole-a-nu.md)) et sert sans état toute requête qui porte le `_meta` moderne. La spec appelle
ça un serveur *dual-era*, et l'autorise. Vérifié sur stdio :

```
→ {"jsonrpc":"2.0","id":1,"method":"server/discover","params":{"_meta":{"…/protocolVersion":"2026-07-28",…}}}
← {"jsonrpc":"2.0","id":1,"result":{"resultType":"complete","supportedVersions":["2026-07-28"],"capabilities":{…},"instructions":"…",…}}
→ {"jsonrpc":"2.0","id":3,"method":"tools/list","params":{"_meta":{"…/protocolVersion":"1900-01-01",…}}}
← {"jsonrpc":"2.0","id":3,"error":{"code":-32022,"message":"Unsupported protocol version","data":{"supported":["2026-07-28"],"requested":"1900-01-01"}}}
```

`supportedVersions` ne liste que la version moderne : les versions legacy ne s'atteignent que par `initialize`.

## Streamable HTTP, version moderne

```
Claude Code ──POST /mcp──► nginx (127.0.0.1:8090) ──► php-fpm ──► routes/ai.php : Mcp::web('/mcp', LibraryServer::class)
                                                                          │
                                                       middlewares : Origin, en-têtes MCP
                                                                          ▼
                                                     LibraryServer ──► tools / resources / prompts (inchangés)
```

**Un seul endpoint, `POST` uniquement.** Chaque message du client est **sa propre requête HTTP**. Il n'y a pas de
connexion qui dure, et rien ne relie deux requêtes entre elles.

| Le client envoie | Le serveur répond |
|---|---|
| une **requête** JSON-RPC | `200`, avec soit `application/json` (un objet), soit `text/event-stream` (un flux SSE propre à cette requête, qui peut porter des notifications de progression avant la réponse finale) |
| une **notification** acceptée | `202 Accepted`, **sans corps** |
| un `GET` ou un `DELETE` | `405 Method Not Allowed` : le flux `GET` et la fermeture de session n'existent plus |
| une méthode inconnue | `404`, avec l'erreur JSON-RPC `-32601` |
| une version non supportée | `400`, avec l'erreur `-32022` |

**Le client annule en fermant le flux.** Comme chaque requête a son propre flux de réponse, fermer la connexion
annule la requête sans ambiguïté. En stdio, où tout passe par un seul tube, il faut une notification
`notifications/cancelled`.

**Les changements de liste** (un tool ajouté, une resource modifiée) arrivent sur un flux SSE ouvert par la requête
`subscriptions/listen`. Notre serveur annonce `listChanged: false` : nous ne nous en servons pas.

### Les en-têtes recopient le corps

Chaque `POST` porte des en-têtes qui **répètent** des champs du corps JSON :

| En-tête | Recopie | Exigé pour |
|---|---|---|
| `MCP-Protocol-Version` | `_meta` `io.modelcontextprotocol/protocolVersion` | toutes les requêtes |
| `Mcp-Method` | `method` | toutes les requêtes |
| `Mcp-Name` | `params.name` ou `params.uri` | `tools/call`, `resources/read`, `prompts/get` |

**Pourquoi répéter.** Un load balancer, une passerelle ou un outil d'observabilité peut aiguiller, limiter ou
journaliser une requête **sans parser le JSON** : par exemple, bloquer `tools/call` de `remove_book` au niveau du
proxy. Mais si le proxy décide sur l'en-tête et que le serveur exécute selon le corps, un attaquant peut envoyer
un en-tête anodin et un corps dangereux. D'où la règle : le serveur **doit** rejeter toute requête dont les
en-têtes manquent ou ne correspondent pas au corps, avec un `400` et l'erreur `-32020` (`HeaderMismatch`).

Le corps reste la source de vérité. Une valeur qui n'est pas de l'ASCII sûr (un nom accentué, par exemple) est
encodée en `=?base64?…?=`.

### Un client legacy sur HTTP

Un client `2025-11-25` envoie `initialize` sans `_meta` moderne. Notre serveur le sert en legacy, mais **sans
jamais attribuer de `Mcp-Session-Id`**. En `2025-11-25`, cette session était facultative pour le serveur : ce
choix reste donc conforme. Le serveur ne vérifie pas les en-têtes MCP d'une requête legacy, puisque cette version
ne les définissait pas.

## La sécurité, même en local

Un serveur HTTP sur `localhost` paraît à l'abri. Il ne l'est pas, à cause du **DNS rebinding** :

1. Tu visites `evil.example`. Son DNS répond d'abord avec la vraie IP du site, avec une durée de cache très courte.
2. La page charge un script, puis le DNS de `evil.example` répond désormais `127.0.0.1`.
3. Le script envoie `POST http://evil.example:8090/mcp`. Pour ton navigateur, c'est la même origine, donc il
   autorise la requête. Pour ton système, c'est ton serveur local. Le script peut appeler `remove_book`.

La seule trace de l'attaque est l'en-tête **`Origin: http://evil.example:8090`**, que le navigateur ajoute
toujours et que le script ne peut pas falsifier. D'où les trois règles de la spec :

1. Le serveur **doit** valider l'en-tête `Origin` et répondre **`403`** s'il est présent et non autorisé.
   **`laravel/mcp` ne le fait pas** : c'est un middleware à nous. Un client hors navigateur, comme Claude Code, n'envoie
   pas d'`Origin` : son absence est donc acceptée.
2. En local, le serveur **devrait** n'écouter que sur `127.0.0.1`, pas sur toutes les interfaces. Notre
   `docker-compose.yml` publiait le port `8090` sur `0.0.0.0`, donc sur tout le réseau local.
3. Le serveur **devrait** authentifier ses clients : c'est l'[étape 5](./roadmap.md).

## Claude Code, branché en HTTP

`.mcp.json` déclare un second serveur, à côté de `library` (stdio) :

```json
"library-http": { "type": "http", "url": "http://localhost:8090/mcp" }
```

`127.0.0.1` et non `localhost` : nginx n'est publié que sur l'IPv4 locale, et `localhost` peut se résoudre en
`::1`. Un serveur ajouté à `.mcp.json` n'est chargé qu'au **démarrage** d'une session Claude Code : `/mcp` reconnecte
les serveurs connus, mais n'en découvre pas de nouveaux.

**Ce que voit le proxy.** nginx journalise chaque requête vers `/mcp` à partir des **seuls en-têtes MCP**, sans lire
le corps (`make mcp-log`). C'est l'usage même pour lequel la spec recopie le corps dans les en-têtes. Au démarrage de
Claude Code, puis à une question sur la bibliothèque :

```
06:58:32 200 POST version=2026-07-28 method=server/discover name=-
06:58:32 200 POST version=2026-07-28 method=prompts/list name=-
06:58:32 200 POST version=2026-07-28 method=tools/list name=-
06:58:32 200 POST version=2026-07-28 method=resources/list name=-
07:00:44 200 POST version=2026-07-28 method=tools/call name=search_books
```

- **Claude Code parle le mode moderne** : aucun `initialize`. Il commence par `server/discover`, facultatif pour le
  client, pour connaître le serveur avant de lui parler.
- **Ses en-têtes correspondent au corps** : tout passe en `200`, aucun `-32020`.
- **Il n'envoie pas d'`Origin`** : c'est pourquoi le middleware doit accepter son absence.
- **Les trois listes partent dans la même seconde** : sans session, rien n'oblige à attendre une réponse avant
  d'envoyer la requête suivante.

Le mode legacy reste servi, pour le MCP Inspector et notre client écrit à la main, mais notre client principal ne
l'utilise pas.

## Stdio et HTTP, côte à côte

| | stdio | Streamable HTTP |
|---|---|---|
| Qui lance le serveur | le host, comme sous-processus | personne : le serveur tourne déjà, derrière nginx |
| Un serveur sert | un seul client | autant de clients que de requêtes |
| Un message | une ligne sur stdin ou stdout | un `POST`, avec sa réponse |
| Annuler | notification `notifications/cancelled` | fermer le flux de réponse |
| Les logs | stderr, qui ne doit rien polluer | les logs Laravel |
| Ce qui casse une session | un `echo` sur stdout | rien de semblable : la réponse HTTP est construite par le framework |
| Exposition | aucune : pas de réseau | un port : `Origin`, interface d'écoute, puis authentification |
| Le coût d'un appel | le processus est déjà démarré, mais il **interroge** stdin (voir les mesures) | une requête PHP-FPM complète, qui démarre Laravel à chaque fois |

## Mesurer plutôt que deviner

`make benchmark` (`client/benchmark.php`) chronomètre le même `tools/call` (`search_books`, « dune ») sur les deux
transports, **depuis l'intérieur du conteneur** : on mesure les transports, pas la redirection de port de Docker.
Une exécution type :

```
First result, from nothing (one run):
  stdio, legacy     155.9 ms
  stdio, modern     131.1 ms
  HTTP, legacy       18.9 ms
  HTTP, modern        9.7 ms
One more tools/call, once warm, back to back (100 calls each):
  stdio          median   15.4 ms   p95   17.4 ms
  HTTP           median   10.3 ms   p95   11.5 ms
One more tools/call, once warm, spaced at random, 0 to 20 ms apart (100 calls each):
  stdio          median   10.6 ms   p95   16.4 ms
  HTTP           median   10.7 ms   p95   12.3 ms
```

**Le premier résultat.** stdio paie le lancement d'un processus PHP et le démarrage de Laravel, sans OPcache (il
est désactivé en CLI) : environ 130 ms. HTTP n'a rien à lancer, PHP-FPM tourne déjà, avec OPcache : environ 10 ms.
Le handshake legacy coûte deux allers-retours de plus : environ 25 ms en stdio, 10 ms en HTTP. C'est le gain
concret de la suppression d'`initialize`.

**À chaud, on attendait stdio largement devant : il ne l'est pas.** Le processus est démarré, la connexion à
Postgres est ouverte, et le traitement d'un `tools/call`, mesuré dans le processus, prend **0,9 ms**. Pourtant, une
requête met 10 à 15 ms. La cause est dans le transport stdio de `laravel/mcp` : il passe stdin en **non bloquant**
et, chaque fois qu'il n'y a rien à lire, **dort 10 ms** (`usleep(10000)`) avant de regarder à nouveau. Une requête
qui arrive pendant ce sommeil attend qu'il finisse. Deux constats l'établissent :

- ramené à `usleep(1000)` le temps d'une expérience, le même `tools/call` passe de 15 ms à 3 ms ;
- dans le conteneur, un `usleep(10000)` dure en réalité **12 ms** (la VM de Docker Desktop arrondit les timers).

D'où les deux scénarios à chaud. **Enchaînées**, les requêtes tombent toujours au début d'un sommeil : environ 12 ms
d'attente, plus le traitement. **Espacées au hasard**, comme celles d'un modèle qui réfléchit entre deux appels,
elles tombent n'importe où dans le cycle : l'attente baisse, mais varie beaucoup, d'une requête à l'autre (le p95)
comme d'une exécution à l'autre (une médiane entre 7 et 12 ms).

Un écart reste **inexpliqué** : `ping`, `server/discover` et `prompts/list` répondent en moins de 0,1 ms sur stdio,
comme s'ils échappaient au sommeil. Les tools et les resources, eux, le subissent toujours. Constaté, pas compris.

**HTTP, de son côté**, paie à chaque requête le démarrage de Laravel (environ 4 ms, mesuré sur `server/discover`, qui
ne touche pas la base). Un `tools/call` en coûte 10 : la différence vient **probablement** de la connexion à Postgres,
ouverte à chaque requête par PHP-FPM, alors que le processus stdio garde la sienne. C'est une hypothèse, pas une
mesure.

**Ce qu'on en retient.** Le transport ne se choisit pas sur la latence : à l'échelle d'un appel de modèle, qui se
compte en secondes, 10 ms ne se voient pas. Mais la mesure a montré deux choses qu'aucune intuition ne donnait :
un transport « sans réseau » peut être plus lent qu'un transport HTTP, et le détail d'implémentation d'un SDK (une
boucle qui interroge au lieu d'attendre) pèse plus que le choix du transport.
