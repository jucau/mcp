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
| Écran d'accord | ✅ vue `mcp.authorize` du SDK | |
| DCR (déprécié) | ✅ `POST /oauth/register` | |
| CIMD côté serveur | ❌ | plus tard (roadmap) |
| Vérifier que le token vise ce serveur (RFC 8707) | ❌ | écart assumé : [ADR-0020](./decisions/0020-passport-serveur-d-autorisation-destinataire-implicite.md) |
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
- **Fermé par défaut.** Le choix ne se fait pas en regardant si un utilisateur est connecté (une route HTTP mal
  protégée retomberait alors sur le lecteur de l'environnement, et servirait la bibliothèque du lecteur configuré à n'importe
  qui). La route HTTP installe explicitement la version « compte authentifié », qui **échoue** s'il n'y a pas de
  compte. Un oubli donne une erreur, jamais une fuite.
- **Le fuseau horaire** devient une donnée du compte, comme l'annonçait
  l'[ADR-0010](./decisions/0010-instants-en-utc-jours-du-lecteur.md) : « aujourd'hui » se calcule dans le fuseau du
  lecteur servi. En stdio, il reste `LIBRARY_READER_TIMEZONE`.

**L'écran de connexion** est minimal : un formulaire e-mail et mot de passe, le temps que Passport demande l'accord.
Pas d'inscription : les comptes de démo sont créés par le seeder, un par lecteur de démo.

## Ce qu'on vérifiera

- `POST /mcp` sans token : `401` et l'en-tête `WWW-Authenticate` qui pointe vers les métadonnées ;
- les deux documents de métadonnées, tels qu'un client les lit ;
- avec le token d'un compte, le serveur sert la bibliothèque de **son** lecteur, et pas celle de l'environnement ;
- en stdio, rien ne change ;
- puis, pour de vrai : Claude Code branché sur `library-http`, la connexion dans le navigateur, et dans le journal
  nginx, le chemin qu'il a suivi (CIMD ou DCR).
