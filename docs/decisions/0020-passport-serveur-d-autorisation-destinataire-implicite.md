# ADR-0020 — Passport comme serveur d'autorisation, et un destinataire de token implicite

- **Statut** : Accepté (2026-10-04)

## Contexte

La spec MCP `2026-07-28` fait du serveur MCP un *resource server* OAuth 2.1. Il lui faut un serveur d'autorisation,
et elle exige qu'un serveur MCP **n'accepte que les tokens émis pour lui** : le client nomme le serveur visé
(paramètre `resource`, RFC 8707), et le serveur vérifie ce destinataire (*audience*).

`laravel/mcp` s'intègre à Passport (`Mcp::oauthRoutes()`, `auth:api`). Passport ne gère pas le paramètre `resource`
et ne met aucun destinataire dans ses tokens. Il ne connaît qu'un scope pour MCP, `mcp:use`.

## Décision

- **Passport est le serveur d'autorisation**, dans la même application que le serveur MCP, branché par
  `Mcp::oauthRoutes()` et `auth:api` sur `Mcp::web`.
- **Le destinataire est implicite** : ce Passport ne délivre de tokens que pour **un seul** serveur, le nôtre. Tout
  token qu'il a signé est donc, de fait, destiné à `/mcp`. On ne vérifie pas de destinataire explicite.
- **Cette décision tombe** le jour où un second serveur (MCP ou API) accepte des tokens de ce même Passport : il
  faudra alors porter le `resource` dans le token et le vérifier.
- **Enregistrement des clients par DCR**, que fournit le SDK, en attendant CIMD (déprécié par la spec, mais c'est le
  seul mécanisme disponible côté serveur). On observera ce que tente Claude Code.

## Conséquences

- (+) Aucune extension de Passport : on utilise ce que le SDK documente.
- (+) L'écart est daté et borné : la condition qui le rend faux est écrite.
- (−) Le serveur ne respecte pas la lettre de la spec sur la vérification du destinataire.
- (−) DCR est déprécié : un client qui n'accepterait que CIMD ne pourrait pas s'enregistrer.

## Alternatives écartées

- **Porter `resource` dans le token et le vérifier** : conforme, mais il faut étendre Passport (grant, claims,
  middleware) pour une menace qui n'existe pas tant qu'il n'y a qu'un serveur.
- **Un scope réservé à ce serveur** (par exemple `marquepage`), vérifié à chaque requête : il protège d'un accident,
  pas d'un serveur malveillant. Le scope demandé vient des métadonnées **du serveur** auquel le client se connecte :
  un serveur malveillant qui partagerait notre serveur d'autorisation pourrait réclamer notre scope, et recevoir un
  token valable chez nous. Le `resource`, lui, vient du **client**, qui le déduit de l'URL où il se connecte :
  aucun serveur ne peut le lui dicter.
- **Un serveur d'autorisation externe** (Keycloak, Auth0…) : plus proche d'un déploiement réel, mais une pièce de
  plus à faire tourner, pour un projet dont l'objet est le protocole MCP.
