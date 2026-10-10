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
| forme invalide (champ manquant, mauvais type, champ inconnu) | `422` | refusée avant le use case, comme dans les tools |
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

Un piège : le jour où le domaine ajoute un refus, l'adaptateur ne le saura pas. Un test parcourt donc **toutes** les
`DomainException` du domaine et vérifie qu'elles ont un statut : un oubli fait échouer la porte, au lieu de finir en
`500`.

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

C'est là que l'API fait sa première découverte : **l'étagère vit dans le mauvais dossier**. `Shelf` est en
`Infrastructure\Mcp`, parce que seules les resources MCP s'en servaient. Or l'étagère est un mot du langage
ubiquitaire (« une recherche pré-filtrée ») : elle n'appartient à aucun protocole. Elle descend en
`Application\Book\Query`. C'est la seule modification attendue du cœur, et elle **corrige** un défaut du premier
adaptateur.

Le contrat de l'API s'écrit dans un document **OpenAPI** versionné, et les tests vérifient que chaque réponse le
respecte : c'est l'équivalent de l'`outputSchema` vérifié par les tests
([ADR-0013](./decisions/0013-output-schema-verifie-par-les-tests.md)).

## Qui demande ? Le même lecteur, choisi par l'API

L'API sert le lecteur **du compte authentifié**, comme `/mcp` en HTTP. Mais rien ne lui est servi d'office
([ADR-0021](./decisions/0021-chaque-transport-choisit-son-lecteur.md)) : c'est un nouveau point d'entrée, il doit
**choisir** son lecteur. Le groupe de routes `/api` branche `AuthenticatedReader`. Sans ce choix, la résolution de
`CurrentReader` échoue : la règle a été écrite pour ce cas.

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

L'API publie ses propres métadonnées de ressource (`/.well-known/oauth-protected-resource/api`) et répond `401` avec
`WWW-Authenticate`, comme `/mcp`. Rien de cela n'est propre à MCP : c'est **OAuth standard** (RFC 9728), que MCP a
choisi de rendre obligatoire. Un client OAuth générique saura découvrir l'API comme Claude Code découvre `/mcp`.

## Ce qu'on vérifiera

- **Le cœur** : `git diff` sur `Domain` vide ; sur `Application`, le seul déplacement de `Shelf`.
- **Les adaptateurs s'ignorent** : PHPArkitect, dans la porte.
- **Chaque endpoint** : son succès, ses refus (statut et *Problem Details*), sa validation de forme, par des tests
  Feature, comme les tools ; et chaque réponse conforme au document OpenAPI.
- **Chaque refus du domaine a un statut** : le test qui les parcourt tous.
- **Le destinataire** : un token MCP refusé par l'API, un token API refusé par `/mcp`, un token sans destinataire
  refusé partout, un token rafraîchi qui garde le sien.
- **Pour de vrai** : Claude Code refait son parcours OAuth sur `library-http`. On vérifiera dans le journal qu'il
  envoie bien `resource`, et qu'il voit la mention du serveur sur l'écran d'accord.
