# 07 — L'authentification

> Étape 5 de la [roadmap](./roadmap.md), deuxième partie. Le domaine sait déjà servir plusieurs lecteurs
> ([ADR-0018](./decisions/0018-un-lecteur-par-livre-sans-compte-dans-le-domaine.md)) : il reste à savoir **qui** fait
> une requête. En HTTP, c'est OAuth 2.1 ; en stdio, c'est l'environnement. Les choix sont actés dans
> [ADR-0019](./decisions/0019-comptes-en-infrastructure-lecteur-ferme-par-defaut.md) et
> [ADR-0020](./decisions/0020-passport-serveur-d-autorisation-destinataire-implicite.md).

## Ce que demande la spec `2026-07-28`

**L'autorisation ne concerne que HTTP.** En stdio, le serveur « ne devrait pas » faire d'OAuth et doit lire ses
identifiants dans l'environnement : c'est l'utilisateur qui a lancé le processus, sur sa machine.

**Trois rôles :**

| Rôle | Ce qu'il fait | Chez nous |
|---|---|---|
| *resource server* | vérifie le token de chaque requête | le serveur MCP (`/mcp`) |
| *authorization server* | authentifie l'utilisateur, recueille son accord, délivre les tokens | Passport, dans la même application |
| client | obtient un token et l'envoie à chaque requête | Claude Code |

**La découverte, sans rien configurer côté client :**

```
Claude Code                         Marque-page (/mcp)                 Passport (/oauth)
    │ POST /mcp (sans token)              │                                  │
    │────────────────────────────────────►│                                  │
    │ 401 WWW-Authenticate: Bearer        │                                  │
    │     resource_metadata="…/.well-known/oauth-protected-resource"        │
    │◄────────────────────────────────────│                                  │
    │ GET /.well-known/oauth-protected-resource                              │
    │────────────────────────────────────►│  « mon serveur d'autorisation    │
    │◄────────────────────────────────────│    est … » (RFC 9728)            │
    │ GET /.well-known/oauth-authorization-server ─────────────────────────►│
    │◄────────────────────────────────────────── endpoints, PKCE, scopes ───│ (RFC 8414)
    │ enregistrement du client (CIMD, sinon DCR) ──────────────────────────►│
    │ navigateur : /oauth/authorize + PKCE + resource ─────────────────────►│ connexion, accord
    │◄──────────────────────────────────────────────── code (redirection) ──│
    │ POST /oauth/token + code_verifier + resource ────────────────────────►│
    │◄───────────────────────────────────────────────────── access token ───│
    │ POST /mcp, Authorization: Bearer …  │                                  │
    │────────────────────────────────────►│ le token désigne un compte,      │
    │◄────────────────────────────────────│ le compte désigne un lecteur     │
```

Les pièces, une par une :

- **Le `401` est le point de départ.** Le client n'a rien à savoir d'avance : l'en-tête `WWW-Authenticate` lui donne
  l'adresse des métadonnées de la ressource.
- **Les métadonnées de la ressource** (RFC 9728) disent quel serveur d'autorisation délivre les tokens pour ce
  serveur MCP. Le serveur MCP **doit** les publier.
- **Les métadonnées du serveur d'autorisation** (RFC 8414) donnent ses endpoints, et ce qu'il accepte : PKCE `S256`,
  scopes, méthodes d'enregistrement.
- **L'enregistrement du client**, par ordre de préférence : un client pré-enregistré, puis un *Client ID Metadata
  Document* (CIMD : le `client_id` est une URL HTTPS vers un document qui décrit le client), puis l'enregistrement
  dynamique (DCR, RFC 7591), désormais **déprécié**.
- **PKCE** protège le code d'autorisation : seul le client qui a créé le `code_challenge` connaît le `code_verifier`
  qui l'échange contre un token.
- **Le paramètre `resource`** (RFC 8707) nomme le serveur MCP auquel le token est destiné. Le serveur MCP **doit**
  refuser un token émis pour un autre.
- **Le token voyage dans `Authorization: Bearer …`, à chaque requête**, jamais dans l'URL. Le protocole étant sans
  état ([06](./06-transport-http.md)), chaque requête se présente seule.
- **Les scopes** disent ce que le token permet. Un `403 insufficient_scope` indique les scopes manquants, et le client
  redemande une autorisation plus large (*step-up*). C'est la troisième partie de l'étape.

## Ce que fournissent `laravel/mcp` et Passport

| Exigence | Fourni | Remarque |
|---|---|---|
| Métadonnées de la ressource et du serveur d'autorisation | ✅ `Mcp::oauthRoutes()` | |
| `401` avec `WWW-Authenticate: Bearer resource_metadata=…` | ✅ middleware du SDK sur `Mcp::web` | |
| Authorization code + PKCE `S256`, refresh tokens | ✅ Passport | |
| Écran d'accord | ⚠️ vue `mcp.authorize` du SDK | remplacée par la nôtre : elle exige un build Vite et un thème Tailwind que nous n'avons pas |
| DCR (déprécié) | ✅ `POST /oauth/register` | |
| CIMD côté serveur | ❌ | plus tard (roadmap) |
| Vérifier que le token vise ce serveur (RFC 8707) | ❌ | écart assumé tant qu'il n'y avait qu'un serveur ([ADR-0020](./decisions/0020-passport-serveur-d-autorisation-destinataire-implicite.md)), refermé à l'arrivée de l'API ([ADR-0026](./decisions/0026-un-destinataire-explicite-par-token.md)) |
| Scopes personnalisés | ❌ un seul, `mcp:use` | la troisième partie devra les ajouter |
| Un écran de connexion | ❌ | à nous : Passport exige un utilisateur connecté pour `/oauth/authorize` |

## Du token au lecteur

Le domaine ne connaît que des `ReaderId` ([ADR-0018](./decisions/0018-un-lecteur-par-livre-sans-compte-dans-le-domaine.md)).
Tout le reste est de l'infrastructure :

- **Un compte** (`User`, au sens de l'authentification) : un e-mail, un mot de passe, un fuseau horaire, et le
  **`reader_id`** du lecteur qu'il incarne. Sa clé primaire reste un entier : Passport distingue un token d'utilisateur
  d'un token de client (*client credentials*, dont le `sub` est l'UUID du client) par la différence de type entre
  les deux identifiants. Le `ReaderId` n'est donc pas la clé du compte, mais une colonne à part.
- **`CurrentReader`** répond à « quel lecteur sert-on ? », de deux façons :
  - **en HTTP**, le compte authentifié par le token (`auth:api`), et **rien d'autre** ;
  - **en stdio**, le lecteur configuré (`LIBRARY_READER`), comme le veut la spec.
- **Fermé par défaut, des deux côtés** ([ADR-0021](./decisions/0021-chaque-transport-choisit-son-lecteur.md)).
  Il n'existe **aucun lecteur par défaut** : c'est `LibraryServer`, au démarrage, qui choisit selon son transport
  (stdio → configuré, HTTP → authentifié). Tout autre point d'entrée (une commande, un job, une autre route) n'a
  pas de lecteur et **échoue**, au lieu de servir le lecteur configuré. Et le lecteur authentifié ne se rabat jamais sur la
  configuration : sans compte, il échoue aussi. Un oubli donne une erreur, jamais une fuite.
- **Pourquoi pas d'OAuth en stdio ?** La frontière de confiance de stdio, c'est la machine : qui peut lancer
  `mcp:start` a déjà le conteneur, le `.env` et la base. Un parcours OAuth devant ce processus ne protégerait rien.
  `LIBRARY_READER` n'authentifie personne : il choisit une bibliothèque, pour quelqu'un qui a déjà tous les droits.
- **Le fuseau horaire** devient une donnée du compte, comme l'annonçait
  l'[ADR-0010](./decisions/0010-instants-en-utc-jours-du-lecteur.md) : « aujourd'hui » se calcule dans le fuseau du
  lecteur servi. En stdio, il reste `LIBRARY_READER_TIMEZONE`.

**L'écran d'accord est le nôtre.** Celui du SDK charge sa feuille de style par `@vite` et suppose un thème Tailwind
(couleurs `bg-card`, `text-muted-foreground`…) : il faudrait Node dans la stack, puis recopier ce thème, pour une
seule page. Le nôtre, en HTML et CSS en ligne comme la page de connexion, dit l'essentiel : **quel client** demande
l'accès, et **pour quel compte**. C'est ce que l'utilisateur doit vérifier avant d'autoriser : c'est son seul
garde-fou contre une demande qu'il n'a pas faite.

**L'écran de connexion** est minimal : un formulaire e-mail et mot de passe, le temps que Passport demande l'accord.
Pas d'inscription : les comptes de démo sont créés par le seeder, un par lecteur
(`julien@marque-page.test`, `camille@marque-page.test`, mot de passe `marque-page`).

**Les outils de dev ont besoin d'un token**, eux aussi. `php artisan library:token <e-mail>` émet un *personal access
token* Passport pour un compte ; `make raw-http-session` et `make benchmark` s'en servent pour un compte de démo. Claude Code,
lui, ne s'en sert jamais : il suit le parcours OAuth complet.

**L'endpoint répond toujours en JSON.** Sans en-tête `Accept`, Laravel renverrait un invité vers la page de connexion
(`302`) : un client MCP ne sait rien en faire. `/mcp` répond donc `401`, avec son `WWW-Authenticate`, quoi que le
client annonce.

## Claude Code, pour de vrai

`library-http` dans `.mcp.json` ne change pas : c'est le serveur qui, désormais, exige un token. Dans Claude Code,
`/mcp` → `library-http` → *Authenticate* ouvre le navigateur. Le journal nginx (`make mcp-log`), qui lit les
chemins sans leur query string et les en-têtes MCP, montre tout le parcours :

```
16:15:27 401 POST /mcp                                    version=2026-07-28 method=server/discover
16:15:27 200 GET  /.well-known/oauth-protected-resource/mcp  version=2025-11-25
16:15:27 200 GET  /.well-known/oauth-authorization-server    version=2025-11-25
   … les deux documents relus deux fois, à 16:15:36 et 16:15:37 …
16:15:37 201 POST /oauth/register
16:15:37 302 GET  /oauth/authorize            (pas connecté : vers /login)
16:18:26 200 GET  /oauth/authorize            (connecté : l'écran d'accord)
16:18:33 302 POST /oauth/authorize            (« Autoriser » : retour vers Claude Code, avec le code)
16:18:33 200 POST /oauth/token                (code + code_verifier → token)
16:18:33 200 POST /mcp                                    version=2026-07-28 method=server/discover
16:18:33 200 POST /mcp                                    version=2026-07-28 method=prompts/list
16:18:33 200 POST /mcp                                    version=2026-07-28 method=resources/list
16:18:33 200 POST /mcp                                    version=2026-07-28 method=tools/list
16:19:06 200 POST /mcp                                    version=2026-07-28 method=tools/call name=search_books
```

- **Le `401` déclenche tout** : Claude Code n'avait rien à savoir d'avance, ni l'adresse de Passport, ni ses endpoints.
- **Pas de CIMD** : notre serveur d'autorisation ne l'annonce pas (`client_id_metadata_document_supported`), et
  Claude Code passe directement à l'**enregistrement dynamique**. Il s'enregistre sous le nom
  « Claude Code (library-http) », en client **public** (sans secret), avec une redirection vers un port local
  (`http://localhost:59438/callback`) : c'est son propre petit serveur HTTP qui recevra le code.
- **Le code ne passe jamais par le serveur MCP** : le navigateur le porte de Passport au callback local de Claude
  Code, qui l'échange aussitôt (`/oauth/token`, avec son `code_verifier` : PKCE).
- **Puis tout recommence, avec le token** : `server/discover`, les trois listes, et l'appel de tool.

Deux observations, **constatées, pas expliquées** :

- les deux documents de métadonnées sont lus **trois fois** en dix secondes ;
- ces lectures portent un en-tête `MCP-Protocol-Version: 2025-11-25`, alors que les requêtes MCP sont en
  `2026-07-28` : la partie OAuth du client semble dater d'avant la nouvelle version.

## Ce qu'on vérifiera

- `POST /mcp` sans token : `401` et l'en-tête `WWW-Authenticate` qui pointe vers les métadonnées ;
- les deux documents de métadonnées, tels qu'un client les lit ;
- avec le token d'un compte, le serveur sert la bibliothèque de **son** lecteur, et pas celle de l'environnement ;
- en stdio, rien ne change ;
- puis, pour de vrai : Claude Code branché sur `library-http`, la connexion dans le navigateur, et dans le journal
  nginx, le chemin qu'il a suivi (CIMD ou DCR).
