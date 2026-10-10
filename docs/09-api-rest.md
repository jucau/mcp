# 09 — Un second adaptateur : l'API REST

> Étape 9 de la [roadmap](./roadmap.md). Jusqu'ici, l'architecture hexagonale
> ([ADR-0006](./decisions/0006-architecture-hexagonale-ciblee.md)) était une promesse : « le serveur MCP n'est qu'un
> adaptateur ». Le passage de stdio à HTTP ne l'a pas mise à l'épreuve, c'était le même adaptateur sur un autre
> transport. Une API REST est un **second adaptateur primaire**, avec une autre sémantique : c'est elle qui vérifie
> la promesse.

## Ce qu'on veut apprendre

- **Le cœur ne bouge pas.** Le critère est mesurable : **aucun diff dans `Domain`**, et dans `Application` seulement
  ce que le second adaptateur révèle d'un concept mal rangé par le premier. Chaque exception se justifie ici.
- **Ce que MCP emprunte à HTTP et à OAuth, et ce qu'il y ajoute.** Une API et un serveur MCP exposent les mêmes use
  cases : leurs différences disent ce qui est propre à MCP.
- **Plusieurs *resource servers* derrière un même serveur d'autorisation** : le destinataire d'un token, que
  l'[ADR-0020](./decisions/0020-passport-serveur-d-autorisation-destinataire-implicite.md) avait laissé implicite.

## Une URL par use case : l'API parle métier

Un tool MCP, c'est **un use case** ([ADR-0008](./decisions/0008-tools-adaptateurs-minces-refus-metier.md)). REST,
dans sa version stricte, pense en **ressources** manipulées par quatre verbes : il faudrait dire « supprimer le
prêt » pour « rendre un livre », et « remplacer le statut » pour « commencer la lecture ». Le langage ubiquitaire
([02](./02-langage-ubiquitaire.md)) y perdrait ses mots.

L'API assume donc un style **RPC pour les actions** : une URL par use case, nommée comme lui
([ADR-0024](./decisions/0024-api-rest-une-url-par-use-case.md)). Mais elle garde les verbes HTTP **là où ils sont
les mots du métier** : lire, c'est `GET` ; ajouter à la bibliothèque, c'est `POST` sur la collection ; retirer, c'est
`DELETE`. Un RPC intégral (`POST /search-books`) jetterait ce que `GET` garantit (sûr, cachable, rejouable par
n'importe quel intermédiaire) sans qu'aucun argument métier ne le justifie.

| Use case | Tool MCP | API | Succès |
|---|---|---|---|
| chercher | `search_books` | `GET /api/books?query=…&reading_status=…&owned=…&lent=…&page=…&per_page=…` | `200`, une page |
| consulter un livre | resource `library://books/{id}` | `GET /api/books/{id}` | `200`, le livre |
| consulter une étagère | resources `library://shelves/…` | `GET /api/shelves/{shelf}` | `200`, les livres |
| ajouter | `add_book` | `POST /api/books` | `201`, `Location`, le livre |
| retirer | `remove_book` | `DELETE /api/books/{id}` | `204` |
| commencer la lecture | `start_reading` | `POST /api/books/{id}/start-reading` | `200`, le livre |
| terminer la lecture | `finish_reading` | `POST /api/books/{id}/finish-reading` | `200`, le livre |
| abandonner | `abandon_reading` | `POST /api/books/{id}/abandon-reading` | `200`, le livre |
| noter | `rate_book` | `POST /api/books/{id}/rate` | `200`, le livre |
| prêter | `lend_book` | `POST /api/books/{id}/lend` | `200`, le livre |
| rendre | `return_book` | `POST /api/books/{id}/return` | `200`, le livre |
| acquérir | `acquire_book` | `POST /api/books/{id}/acquire` | `200`, le livre |

L'`id` passe dans l'URL, le reste dans un corps JSON (`{"borrower": "Marie", "on": "2026-10-10"}`). Comme les tools,
chaque action renvoie le livre mis à jour : le client n'a pas à le relire.

### Le prix du RPC : l'idempotence ne se voit plus

Huit tools sur dix sont annotés `idempotentHint` ([04](./04-les-trois-primitives.md)) : prêter deux fois le même
livre ne le prête pas deux fois. En HTTP, `POST` n'est **pas** idempotent par définition (RFC 9110) : un client ou un
proxy ne rejouera jamais un `POST` de lui-même après une coupure. Le métier reste idempotent, mais **le protocole ne le
dit plus**. C'est le coût assumé du style RPC ; un en-tête `Idempotency-Key` le compenserait, il n'est pas prévu.

### Une annotation est un indice, un verbe est une garantie

| Ce qu'on dit | MCP | HTTP |
|---|---|---|
| ne change rien | `readOnlyHint` | `GET` est *sûr* |
| se rejoue sans effet de plus | `idempotentHint` | `PUT` et `DELETE` sont *idempotents* |
| détruit de l'information | `destructiveHint` | rien : `DELETE` ne dit pas qu'on perd quelque chose, `POST /rate` non plus |
| touche le monde extérieur | `openWorldHint` | rien |

**Idempotent ne veut pas dire « même réponse ».** Un second `DELETE /api/books/{id}` répond `404`, plus `204` : le
livre n'est plus là. La RFC 9110 parle de l'**effet sur le serveur**, pas de la réponse : après un ou deux `DELETE`,
l'état est le même, le livre n'existe plus. Le tool `remove_book` est annoté `idempotentHint` pour la même raison, et
son second appel est lui aussi un refus.

Les annotations MCP sont des **indices** pour un modèle et son client, qui s'en servent pour demander une
confirmation : la spec interdit d'en faire une décision de sécurité. Les verbes HTTP sont des **garanties** sur
lesquelles s'appuient des machines (caches, proxies, navigateurs). Et MCP en dit plus que HTTP sur ce qui compte pour
un humain : qu'une note écrasée est perdue.

## Les refus : un statut pour le programme, un message pour l'humain

En MCP, un refus métier est un résultat `isError` avec un message : **le modèle le lit** et s'adapte. Personne n'a
besoin de le classer. En HTTP, le client est un **programme** : il branche sur le statut avant de lire quoi que ce
soit. L'adaptateur doit donc classer chaque refus du domaine.

| Refus du domaine | Statut | Pourquoi |
|---|---|---|
| `UnknownBook`, `InvalidBookId` (dans l'URL) | `404` | l'URL ne désigne aucun livre du lecteur |
| `ReadingTransitionNotAllowed`, `BookAlreadyLent`, `BookNotLent`, `BookLent`, `BookNotOwned`, `BookNotFinished`, `DuplicateIsbn` | `409` | la demande est bien formée, mais l'état du livre l'interdit |
| `InvalidIsbn`, `InvalidRating`, `InvalidText`, `FinishedBeforeStarted` | `422` | une valeur fournie ne respecte pas une règle du domaine |
| `InvalidReaderId` | `500` | le lecteur vient du compte, pas de la requête : un lecteur invalide est une panne du serveur, et son message ne sort pas |
| forme invalide (champ manquant, mauvais type) | `422` | refusée avant le use case, comme dans les tools ; un champ inconnu est ignoré, comme dans les tools |
| aucun token, token invalide ou destiné à un autre serveur | `401` | voir plus bas |

Le corps suit le format standard des erreurs HTTP, les *Problem Details* (RFC 9457, `application/problem+json`) :

```json
{
  "type": "https://marque-page.test/problems/book-already-lent",
  "title": "Book already lent",
  "status": 409,
  "detail": "\"Dune\" is already lent to Marie."
}
```

Le `detail` est **le message du domaine**, le même que lit le modèle en MCP : le métier n'écrit son refus qu'une
fois. Seuls le `type` et le statut sont propres à HTTP.

Un refus de **forme** (la validation de Laravel) prend le même format, avec le détail par champ dans un membre
`errors`, une extension que la RFC 9457 permet. Le `401`, lui, garde la réponse de Laravel : il dit seulement qu'il
faut un token, et c'est l'en-tête `WWW-Authenticate` qui dit où l'obtenir.

Un piège : le jour où le domaine ajoute un refus, l'adaptateur ne le saura pas. Un test parcourt donc **toutes** les
`DomainException` du domaine et vérifie qu'elles ont un statut : un oubli fait échouer la porte, au lieu de finir en
`500`. Et seules les exceptions **du domaine** deviennent des *Problem Details* : `DomainException` est une classe de
PHP, qu'une bibliothèque peut lever aussi, et son message n'est pas fait pour sortir.

## La forme des entrées : HTTP n'a pas de types

Les arguments d'un tool arrivent en JSON, typés : `"owned": true` est un booléen, et le tool le valide en strict
(`boolean:strict`). Le corps JSON d'un `POST` se valide de la même façon. Mais la **query string** d'un `GET` n'a
que des chaînes : `?owned=true` donne `"true"`. L'adaptateur doit y convertir, explicitement et strictement
(`true`/`false`, rien d'autre), là où MCP n'avait rien à faire. C'est l'un des rares endroits où HTTP est moins
précis que JSON-RPC.

## Chaque adaptateur possède son contrat

L'API et le serveur MCP présentent le même `BookView`, avec presque les mêmes champs. Ils ne partagent pourtant
**pas** de presenter ([ADR-0025](./decisions/0025-chaque-adaptateur-possede-son-contrat.md)) : le contrat MCP est
écrit pour un modèle (des descriptions, un `outputSchema`), celui de l'API pour un programme qui s'y attache. Les
lier, c'est s'interdire de faire évoluer l'un sans casser l'autre. Onze lignes dupliquées coûtent moins cher que ce
couplage.

Chaque adaptateur présente avec l'outil de son monde. MCP a son `BookPresenter`, qui sert aussi l'`outputSchema`.
L'API a des **`JsonResource`** Laravel : un livre est emballé dans `data`, et une page, convertie en
`LengthAwarePaginator` par l'adaptateur, reçoit les `meta` et `links` standard. La `JsonResource` reçoit un
`BookView` typé : sa propriété `$resource` est `mixed` dans le framework, et l'accès magique (`$this->title`)
échapperait à PHPStan.

> **Trois sens pour un même mot.** Une *resource* MCP (des données qu'on lit par URI), une *ressource* OAuth (le
> serveur auquel un token est destiné) et une `JsonResource` Laravel (la mise en forme d'une réponse JSON) n'ont rien
> en commun. Le code dit toujours laquelle : `Mcp\Resources\BookResource`, `Api\Resources\BookResource`, et le
> `resource` d'un token.

Les adaptateurs **s'ignorent** : rien dans `Infrastructure\Api` ne dépend d'`Infrastructure\Mcp`, et
réciproquement. PHPArkitect le vérifie, comme il vérifie déjà que le cœur ne connaît aucun des deux.

Ce qui se partage, c'est ce qui **n'est pas un contrat**. Lire un tableau déjà validé en vérifiant le type de chaque
valeur (`ValidatedData`) ne regarde aucun client : tools et contrôleurs s'en servent tous les deux, depuis
`Infrastructure`. Le `ValidatedInput` de Laravel (`$request->safe()`) ne convenait pas : il **convertit** au lieu de
vérifier, et rend `''` pour une valeur absente, là où un ISBN inconnu doit rester `null`. La règle tient en une
question : si l'un des deux adaptateurs voulait changer cette brique, l'autre devrait-il suivre ? Pour un presenter,
non ; pour la lecture typée, oui.

C'est là que l'API fait sa première découverte : **l'étagère vit dans le mauvais dossier**. `Shelf` est en
`Infrastructure\Mcp`, parce que seules les resources MCP s'en servaient. Or l'étagère est un mot du langage
ubiquitaire (« une recherche pré-filtrée ») : elle n'appartient à aucun protocole. Elle descend en
`Application\Book\Query`. C'est la seule modification attendue du cœur, et elle **corrige** un défaut du premier
adaptateur.

Le déplacement a une conséquence qu'on n'avait pas vue : dans `Application`, `Shelf` passe sous le regard
d'**Infection**, qui ne joue que la suite Unit. Les étagères n'étaient testées qu'en Feature, à travers Eloquent :
chaque mutant de leur définition (`owned: true` changé en `false`, par exemple) aurait survécu. Un test Unit fixe donc la
définition de chaque étagère, comme `BookSearchTest` fixe celle d'une recherche. Il répète le code, et c'est voulu :
le code **est** ici une définition du glossaire. Un double in-memory de `BookQueries` aurait permis de tester les
étagères par leur effet, mais au prix d'une recherche insensible aux accents réécrite en mémoire, pour quatre lignes.

## Le contrat OpenAPI : généré depuis le code, vérifié par chaque test

Le contrat de l'API est un document **OpenAPI 3.1** (`docs/api/openapi.json`), **généré** par Scramble depuis le code
(`make openapi`, dans la porte) et versionné : un changement de l'API se voit dans le diff. Chaque test de l'API le
vérifie avec Spectator. C'est l'équivalent de l'`outputSchema` vérifié par les tests
([ADR-0013](./decisions/0013-output-schema-verifie-par-les-tests.md)), et le choix des outils est dans
l'[ADR-0028](./decisions/0028-openapi-genere-par-scramble-verifie-par-spectator.md).

**Ce que Scramble lit seul**, sans rien exécuter : les routes, les corps validés et leurs champs requis, les lectures
de la query string, le `toArray()` de `BookResource` (nullables compris), la pagination, le `201` de l'ajout, le `204`
du retrait, l'enum des étagères (`ShelfName`, liée par Laravel à la route) et celle des statuts de lecture.

**Là où l'on reprend la main**, du plus léger au plus lourd :

| Besoin | Prise | Où |
|---|---|---|
| quels refus pour quel endpoint | un `@throws` par refus, sur le contrôleur | `Api\Controllers\*` |
| refus et validation en *Problem Details* | deux `ExceptionToResponseExtension` | `Api\OpenApi\RefusalToProblem`, `ValidationToProblem` |
| un paramètre de query n'est jamais `null` | un *operation transformer* | `Api\OpenApi\QueryParametersAreNeverNull` |
| le titre, la sécurité OAuth2 | un *document transformer* | `Api\OpenApi\DescribeTheApi` |

Trois enseignements de l'essai, qui valent au-delà de cet outil :

- **Une seule table pour la réponse et le contrat.** Les extensions demandent à `ProblemDetails` le statut, le `type`
  et le `title` de chaque refus : ce que l'API répond et ce que le contrat annonce ne peuvent pas diverger.
- **Un exemple ne se vérifie pas.** Avec le `type` d'un refus documenté en *exemple*, un `@throws BookNotOwned` oublié
  passait : `BookAlreadyLent` documentait aussi un `409`, de même forme. Documentés en **valeurs imposées**, le `type`
  et le `title` désignent un refus précis, et l'oubli fait échouer le test qui le provoque.
- **Le contrat avait tort, l'outil de test aussi.** Spectator refusait `?per_page=1` : il ne convertit une query
  string en entier que si le type est exactement `integer`, pas `["integer", "null"]` (sa lecture de la 3.1 a cette
  limite). Mais ce `null` n'avait rien à faire là : une query string ne porte pas `null`, un paramètre y est présent ou
  absent. Le corriger dans le contrat a réglé les deux.

**La règle des tests** (`RespectsTheApiContract`) : toute **réponse** doit respecter le contrat, et toute **requête**
que l'API accepte aussi. Une requête refusée exprès par un test (un `owned=yes`, un corps sans `title`) sort du contrat
par construction : seule sa réponse est vérifiée. La limite, assumée : un refus qu'aucun test ne provoque peut manquer
au contrat sans que rien ne le signale.

## Qui demande ? Le même lecteur, choisi par l'API

L'API sert le lecteur **du compte authentifié**, comme `/mcp` en HTTP. Mais rien ne lui est servi d'office
([ADR-0021](./decisions/0021-chaque-transport-choisit-son-lecteur.md)) : c'est un nouveau point d'entrée, il doit
**choisir** son lecteur. Le groupe de routes `/api` branche `AuthenticatedReader` par un middleware. Sans ce choix,
la résolution de `CurrentReader` échoue : la règle a été écrite pour ce cas.

Deux pièges de Laravel, rencontrés en chemin :

- **Un contrôleur est instancié avant ses middlewares** : Laravel le construit pour lire les middlewares qu'il
  déclare. Un `CurrentReader` injecté dans le constructeur serait donc résolu avant que le groupe ait choisi le
  lecteur. Les contrôleurs le reçoivent dans leur méthode.
- **Les tests partageaient le lecteur choisi.** `TestCase` lie `ConfiguredReader` sur un lecteur de test, et les
  tests de l'API s'authentifiaient avec ce même lecteur : un groupe `/api` qui aurait oublié de choisir son lecteur
  serait passé inaperçu. Un test s'authentifie donc avec le compte d'un **autre** lecteur, comme pour `/mcp`.

L'`Origin` n'est pas vérifié sur l'API : cette protection de `/mcp` vise le *DNS rebinding* contre un serveur local
qu'un navigateur atteindrait avec les droits de l'utilisateur
([ADR-0016](./decisions/0016-http-sans-etat-deux-eres-origin-valide.md)). Une API qui n'accepte qu'un token porté
par `Authorization`, jamais un cookie, ne donne rien à un site malveillant. La question des navigateurs reviendra
avec le frontend (CORS).

## Deux *resource servers*, un serveur d'autorisation : à qui est destiné un token ?

C'est le cœur de l'étape. Jusqu'ici, Passport ne délivrait de tokens que pour **un** serveur, `/mcp` : tout token
signé lui était, de fait, destiné. L'ADR-0020 l'avait écrit, avec sa condition de fin : « le jour où un second
serveur (MCP ou API) accepte des tokens de ce même Passport ». Ce jour est arrivé.

### Ce que demande la spec, et pourquoi

Le client nomme le serveur visé par le paramètre **`resource`** (RFC 8707), à l'autorisation et à l'échange du code.
Le serveur MCP **doit** refuser un token émis pour un autre. Le risque visé est la **réutilisation** : un serveur
qui a reçu un token (parce qu'un client le lui a présenté) s'en sert ailleurs, avec les droits de l'utilisateur.

Soyons honnêtes sur la menace **chez nous** : `/mcp` et `/api` sont la même application, la même base, les mêmes use
cases. Un token MCP qui ouvrirait l'API ne donnerait **aucun droit de plus**. Le gain n'est donc pas une faille
fermée aujourd'hui. Il est ailleurs :

- **la conformité** : la spec MCP l'exige, sans condition de menace ;
- **le consentement** : l'utilisateur a autorisé « Claude Code, via MCP » ; il n'a rien autorisé d'autre ;
- **l'avenir** : le jour où l'API expose ce que MCP n'expose pas (le compte, l'export), le token MCP ne doit pas y
  suffire.

### Pourquoi pas une seule ressource, l'application entière ?

Ce serait le plus simple : un destinataire, `http://localhost:8090`. Mais la spec demande au client MCP de vérifier
que le `resource` annoncé par les métadonnées correspond à **l'URL où il se connecte**, `…/mcp` : c'est sa protection
contre un serveur qui se ferait passer pour un autre (RFC 9728). Une ressource « application » ne passerait pas
cette vérification.

### Pourquoi pas des scopes ?

Un scope par serveur (`mcp:use`, `api:use`) dit **ce que** le token permet, pas **où** il vaut. L'ADR-0020 l'avait
écarté : c'est le serveur qui annonce les scopes à demander, alors que c'est le **client** qui choisit le `resource`,
d'après l'URL où il se connecte. Un serveur ne peut pas dicter le second. Les deux notions se complètent, elles ne se
remplacent pas ; les scopes par tool restent [pour plus tard](./roadmap.md).

### Le piège : `aud` est déjà pris

Le standard des tokens JWT (RFC 9068) met le destinataire dans le claim **`aud`**. Passport, lui, y met
**l'identifiant du client** (`league/oauth2-server`, `AccessTokenTrait::convertToJWT()`), et s'en sert pour
retrouver le client (`BearerTokenValidator`, `TokenGuard::client()`). Y mettre le `resource` casserait Passport. Le
mot est le même, le sens est l'inverse : chez Passport, `aud` dit « **qui** a demandé », dans la RFC « **pour qui**
c'est ».

### La décision : le destinataire enregistré avec le token

Passport ne connaît pas le paramètre `resource`, mais il **enregistre chaque token en base** (`oauth_access_tokens`)
et la relit à chaque requête pour savoir s'il est révoqué. Le destinataire y trouve sa place
([ADR-0026](./decisions/0026-un-destinataire-explicite-par-token.md)) :

1. **À l'autorisation**, le `resource` demandé est validé (c'est l'une de nos deux ressources) et **affiché sur
   l'écran d'accord** : l'utilisateur voit à quoi il donne accès, plus seulement à qui.
2. **À l'émission du token**, le `resource` est enregistré avec lui ; un token rafraîchi garde le sien.
3. **À chaque requête**, `/mcp` et `/api` vérifient que le token leur est destiné, sinon `401`.
4. **Fermé par défaut** : un token sans destinataire n'ouvre **rien**. Le `library:token` des outils de dev prend
   la ressource visée en option.

### Ce qu'envoie vraiment Claude Code

Avant de coder quoi que ce soit, on a regardé un vrai client. Le « fermé par défaut » suppose que le client envoie
`resource` : s'il ne l'envoyait pas, la décision lui fermerait la porte. Un journal nginx temporaire, qui lisait la
query string et le corps des requêtes `/oauth/*`, a capturé le parcours OAuth complet de Claude Code (codes et
`code_verifier` coupés) :

```
POST /oauth/register   {"client_name":"Claude Code (library-http)","redirect_uris":["http://localhost:62343/callback"],
                        "token_endpoint_auth_method":"none","application_type":"native","scope":"mcp:use", …}
GET  /oauth/authorize  response_type=code&client_id=01a12749-…&code_challenge=…&code_challenge_method=S256
                       &redirect_uri=http://localhost:62343/callback&state=…&scope=mcp:use
                       &resource=http://localhost:8090/mcp
POST /oauth/token      grant_type=authorization_code&code=…&code_verifier=…&redirect_uri=…&client_id=01a12749-…
                       &resource=http://localhost:8090/mcp
```

- **Claude Code envoie `resource` aux deux étapes**, à l'autorisation et à l'échange du code, comme le demande la
  RFC 8707. Le « fermé par défaut » ne le bloquera pas.
- **La valeur est l'URL configurée chez le client.** Un premier essai, avec `http://127.0.0.1:8090/mcp` configuré,
  a envoyé `resource=http://127.0.0.1:8090/mcp`. Les métadonnées, lues à la même adresse, annonçaient elles aussi
  `127.0.0.1` : on ne peut rien en conclure sur la vérification que fait Claude Code.
- **Les métadonnées se fient à l'en-tête `Host`.** `laravel/mcp` construit le `resource` et l'adresse du serveur
  d'autorisation avec `url()`, qui suit le `Host` de la requête, pas `APP_URL`. Une requête avec
  `Host: evil.example` reçoit des métadonnées qui désignent `http://evil.example` comme serveur d'autorisation.
  Le client ne choisit pas le `Host` qu'il envoie, mais un cache placé devant le serveur pourrait garder et
  resservir une telle réponse.
- **Conséquence pour la validation** : `localhost` et `127.0.0.1` désignent la même machine, mais ce sont deux
  chaînes différentes. Le serveur compare le `resource` reçu, **exactement**, à l'URI canonique de chacune de ses
  ressources, dérivée d'`APP_URL` et jamais de la requête. Ce n'est pas au serveur d'interpréter les adresses. Le
  `.mcp.json` du projet configure donc `localhost`, comme `APP_URL`.

L'application force donc la racine de toutes ses URL sur `APP_URL`
([ADR-0027](./decisions/0027-urls-annoncees-depuis-app-url.md)) : les métadonnées ne se laissent plus dicter par
la requête, et chaque ressource a **une** URI canonique.

### Comment, chez Passport : suivre le `resource` de bout en bout

Passport ignore `resource`, mais il résout ses repositories et ses contrôleurs par le container, et aucune de ces
classes n'est `final`. On les **étend et on les rebinde**, sans toucher à `vendor`. Le `resource` traverse le
parcours ainsi :

| Moment | Où l'on s'accroche | Ce qui s'y passe |
|---|---|---|
| `GET /oauth/authorize` | `AuthorizationController::authorize` | le `resource` est validé (absent ou inconnu : `400 invalid_target`), gardé en session et montré sur l'écran d'accord |
| « Autoriser », ou un accord déjà donné | `AuthCodeRepository::persistNewAuthCode` | le `resource` est enregistré sur le **code** (`oauth_auth_codes.resource`) |
| `POST /oauth/token`, avec le code | `ScopeRepository::finalizeScopes` | le seul point qui reçoit l'id du code **avant** l'émission : le `resource` du code est repris, et un `resource` différent dans la requête est refusé (`invalid_target`) |
| `POST /oauth/token`, en rafraîchissement | `AccessTokenRepository::revokeAccessToken` | l'ancien token est révoqué juste avant l'émission du nouveau : son `resource` est repris |
| émission du token | `AccessTokenRepository::persistNewAccessToken` | le `resource` est enregistré sur le **token** (`oauth_access_tokens.resource`) |
| chaque requête sur `/mcp` | un middleware après `auth:api` | le `resource` du token doit être l'URI canonique de `/mcp`, sinon `401` |

Entre ces points, le `resource` voyage dans un **`ResourceIndicator`** (du nom de la RFC 8707, *Resource
Indicators*), à **usage unique** : celui qui le lit le vide. Une valeur oubliée ne peut pas se retrouver sur le
token suivant, même quand plusieurs requêtes passent par le même processus (les tests, ou Octane).

**La fragilité, assumée et surveillée.** Ce montage s'appuie sur l'**ordre des appels** de
`league/oauth2-server` : `finalizeScopes` avant l'émission dans l'échange du code, `revokeAccessToken` avant
l'émission dans le rafraîchissement. Rien dans leur contrat ne le garantit. Les tests jouent donc le **vrai parcours
OAuth**, de l'autorisation au rafraîchissement : une montée de version qui changerait cet ordre fait échouer la porte,
au lieu de produire des tokens sans destinataire.

Trois choix plus fins :

- **Le rafraîchissement garde le `resource` d'origine** et ignore celui de la requête : la RFC 8707 permet de
  restreindre un token à un sous-ensemble, et avec une seule ressource par token, il n'y a rien à restreindre.
- **Une erreur `invalid_target` n'est pas renvoyée au client par redirection** : on la lève avant que Passport n'ait
  validé le client et sa `redirect_uri`, et rediriger vers une adresse non validée serait une redirection ouverte.
- **Un accord vaut pour un serveur.** Passport saute l'écran d'accord quand le client a déjà un token actif avec les
  mêmes scopes (`hasGrantedScopes`), sans regarder pour quel serveur. Notre contrôleur y ajoute le `resource` : un
  accord donné pour `/mcp` ne dispense pas de celui pour `/api`.

L'API publie ses propres métadonnées de ressource (`/.well-known/oauth-protected-resource/api`) et répond `401` avec
`WWW-Authenticate`, comme `/mcp`. Rien de cela n'est propre à MCP : c'est **OAuth standard** (RFC 9728), que MCP a
choisi de rendre obligatoire. Un client OAuth générique saura découvrir l'API comme Claude Code découvre `/mcp`.

Le middleware qui ajoute cet en-tête est déclaré **dans la liste de priorité** de Laravel, juste avant
l'authentification. Sinon, Laravel trie `auth:api` devant lui (le groupe `api` contient un middleware prioritaire), et
le `401` partirait sans dire où obtenir un token. Le test sans token l'a montré, alors qu'un token mal destiné, refusé
plus loin, recevait bien l'en-tête.

**Un écart connu, laissé de côté.** Ces métadonnées sont celles de `laravel/mcp`, qui les sert pour **n'importe quel**
chemin (`/.well-known/oauth-protected-resource/n-importe-quoi` annonce une ressource qui n'existe pas) et y annonce le
scope `mcp:use`, qui ne veut rien dire pour l'API. Sans conséquence aujourd'hui : aucun token ne vaut pour un chemin
inconnu, et l'API ne vérifie pas de scope.

## Pour de vrai : Claude Code refait son parcours

Une fois le destinataire en place, le token obtenu le matin par Claude Code, sans destinataire, ne valait plus rien.
Le journal nginx (`make mcp-log`) montre comment Claude Code s'en est remis, sans qu'on touche à sa configuration :

```
22:02:43 401 POST /oauth/token                  (rafraîchissement refusé : son client n'existe plus)
22:02:43 401 POST /mcp     method=server/discover
22:02:43 200 GET  /.well-known/oauth-protected-resource/mcp
22:02:48 201 POST /oauth/register               (il se réenregistre)
22:02:48 200 GET  /oauth/authorize              (l'écran d'accord, qui nomme le serveur)
22:02:52 302 POST /oauth/authorize
22:02:52 200 POST /oauth/token
22:02:52 200 POST /mcp     method=server/discover
22:03:12 200 POST /mcp     method=tools/call name=search_books
```

- **Il a d'abord tenté de rafraîchir son token**, et Passport a répondu `401` (`invalid_client`) : entre-temps, un
  `make seed` (`migrate:fresh`) avait effacé la base, et avec elle le client que Claude Code avait enregistré. Le
  client n'existait plus, son *refresh token* non plus.
- **Il a recommencé toute la découverte**, à partir du `401` de `/mcp` : métadonnées, nouvel enregistrement (DCR),
  autorisation. Un client MCP n'a rien à garder qu'il ne sache redécouvrir.
- **Le nouveau token porte son destinataire**, relu en base : `resource = http://localhost:8090/mcp`, comme le code
  d'autorisation qui l'a précédé.

Le volet `/api` attend un vrai client OAuth, le frontend : les tests jouent son parcours complet, et un token émis par
`library:token --resource=api` ouvre l'API, mais aucun client n'a encore suivi la découverte jusqu'à elle.

## Ce qu'on vérifiera

- **Le cœur** : `git diff` sur `Domain` vide ; sur `Application`, le seul déplacement de `Shelf`.
- **Les adaptateurs s'ignorent** : PHPArkitect, dans la porte.
- **Chaque endpoint** : son succès, ses refus (statut et *Problem Details*), sa validation de forme, par des tests
  Feature, comme les tools ; et chaque réponse conforme au contrat OpenAPI généré, par Spectator.
- **Chaque refus du domaine a un statut** : le test qui les parcourt tous.
- **Le destinataire** : un token MCP refusé par l'API, un token API refusé par `/mcp`, un token sans destinataire
  refusé partout, un token rafraîchi qui garde le sien.
- **Pour de vrai** : Claude Code refait son parcours OAuth sur `library-http`. On vérifiera dans le journal qu'il
  envoie bien `resource`, et qu'il voit la mention du serveur sur l'écran d'accord.

## Bilan de l'étape

### La promesse de l'hexagone, mesurée

Depuis le premier commit de l'étape (`git diff 7ec9b04^ HEAD`) :

| Zone | Diff | Ce que c'est |
|---|---|---|
| `Domain` | **aucune ligne** | |
| `Application` | +25 | `Shelf`, déplacé depuis `Infrastructure\Mcp` : un rangement corrigé, pas un ajout |
| `Infrastructure\Mcp` | +26 −117 | des imports : `Shelf` et la lecture typée (`ValidatedData`) partent vers le cœur et vers `Infrastructure` |
| `Infrastructure\Api` | +935 (22 fichiers) | le second adaptateur, entier |
| `Infrastructure\Auth` | +346 | le destinataire des tokens, qui sert les deux adaptateurs |

L'API expose les **dix mêmes use cases** que le serveur MCP sans qu'aucun handler ni aucune règle ne change. La promesse
tient. Et le seul écart **corrige** le premier adaptateur au lieu de plier le cœur au second.

### Ce que le second adaptateur a appris du premier

Un second client révèle ce que le premier rendait invisible :

- **Un concept mal rangé** : l'étagère, un mot du glossaire, vivait dans le dossier MCP parce que seul MCP s'en servait.
- **Une brique crue spécifique** : `ToolArguments` n'avait rien de MCP. C'était la lecture typée d'un tableau validé.
- **Un écart de conformité** : le destinataire implicite des tokens ([ADR-0020](./decisions/0020-passport-serveur-d-autorisation-destinataire-implicite.md))
  tenait parce qu'il n'y avait qu'un serveur. L'API l'a fait tomber, et **`/mcp` en sort conforme à la spec**, ce qu'il
  n'était pas.
- **Une confiance excessive** : en préparant la validation du `resource`, on a vu que les métadonnées OAuth se fiaient
  à l'en-tête `Host` ([ADR-0027](./decisions/0027-urls-annoncees-depuis-app-url.md)).

### Ce que MCP fait autrement que HTTP

| Sujet | MCP | REST |
|---|---|---|
| Qui lit la réponse | un **modèle** : il lit le message et s'adapte | un **programme** : il branche sur un statut |
| Un refus | `isError` et un message | un statut (`404`, `409`, `422`) et des *Problem Details*, **avec le même message** |
| La nature d'une action | des **indices** (`readOnlyHint`, `destructiveHint`…), pour demander une confirmation | des **garanties** des verbes (`GET` sûr, `DELETE` idempotent), sur lesquelles s'appuient caches et proxies |
| Ce qui compte pour un humain | `destructiveHint` : une note écrasée est perdue | rien ne le dit |
| Les types des entrées | du JSON typé, jusqu'au booléen | une query string, où tout est texte |
| Le contrat | **dans le protocole** : `tools/list` donne les schémas, le client les découvre | **à côté** : un document OpenAPI, publié et versionné |
| L'authentification | OAuth 2.1, découverte par le `401` et les métadonnées | **la même**, mot pour mot (RFC 9728, 8707) |

La dernière ligne est la plus instructive : sur l'authentification, MCP n'invente rien. Il **rend obligatoire** de
l'OAuth standard qu'une API REST peut adopter telle quelle. Le contrat est la vraie différence de nature : un client
MCP n'a besoin d'aucune documentation, il interroge le serveur.

### Les pièges, pour la prochaine fois

- **Laravel** : un contrôleur est instancié **avant** ses middlewares, et l'authentification est triée devant tout
  middleware absent de sa liste de priorité.
- **Les tests** : une classe de base qui lie un lecteur pour tous masque la règle « fermé par défaut ». C'est
  arrivé deux fois : avec le seeder, dont `make seed` était cassé depuis l'ADR-0021, et avec l'API, où le test qui
  tranche s'authentifie avec un **autre** lecteur.
- **PHP** : `DomainException` est une classe standard, qu'une bibliothèque peut lever. Seules celles du domaine
  deviennent des *Problem Details*.
- **Les contrats** : un exemple ne se vérifie pas, et un `null` n'a pas de sens dans une query string.
- **L'observation** : l'essai de Claude Code en `127.0.0.1` ne permettait pas de conclure ce que j'en ai d'abord
  tiré. Il a fallu corriger la doc. Une observation dit ce qu'elle montre, pas plus.

### La méthode : où Infection ne regarde pas

Infection ne mute que `Domain` et `Application`. Pour l'`Infrastructure`, on a cassé **à la main** chaque point clé :
les accroches dans Passport, le choix du lecteur, le contrôle du destinataire, la lecture de la query string, les dates
des actions, les `@throws`. Sur une trentaine de mutants, **quatre survivaient** (`?owned=true` lu comme `false`, la
date ignorée de `start-reading` et d'`abandon-reading`, un `@throws` oublié). Chacun a reçu son test.

Ces trous étaient des comportements que le code faisait bien, mais qu'aucun test ne demandait.

**Étendre Infection à l'`Infrastructure` a été envisagé, puis écarté.** Le principe tient : le mutation testing mesure
les tests, quel que soit leur niveau. Le coût, lui, ne tient pas :

- **le temps** : chaque mutant paie le démarrage de Laravel et une base réelle, pour plusieurs centaines de mutants ;
- **la base partagée** : des processus parallèles sur la même base de test donnent des échecs aléatoires, que
  `timeoutsAsEscaped` transformerait en faux survivants ;
- **le bruit** : une grande part de l'`Infrastructure` est de la colle (configuration, descriptions, ordre des
  middlewares), où un mutant survivant est rarement un trou. Le MSI à 100 % n'y aurait pas de sens.

La pratique retenue reste celle de cette étape : **casser à la main les points clés** d'un adaptateur quand on l'écrit,
et donner un test à chaque mutant qui survit.

### Ce qui reste ouvert

- **La vérification réelle côté API**, quand un vrai client OAuth (le frontend) suivra la découverte jusqu'à `/api`.
- **Les métadonnées de `laravel/mcp`**, servies pour n'importe quel chemin et qui annoncent `mcp:use` à l'API.
- **Un refus qu'aucun test ne provoque** peut manquer au contrat OpenAPI sans que rien ne le signale.
- **Scramble est en 0.x**, et son API d'extension peut changer.

