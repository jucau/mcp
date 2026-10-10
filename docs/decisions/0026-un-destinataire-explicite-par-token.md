# ADR-0026 — Un destinataire explicite par token, enregistré avec lui

- **Statut** : Accepté (2026-10-10). Remplace, dans
  l'[ADR-0020](./0020-passport-serveur-d-autorisation-destinataire-implicite.md), le destinataire implicite.

## Contexte

L'ADR-0020 laissait le destinataire des tokens implicite, tant que Passport ne servait qu'un serveur, et écrivait sa
condition de fin : un second serveur qui accepte ses tokens. L'API REST est ce second serveur
([09](../09-api-rest.md)).

La spec MCP exige que le serveur MCP refuse un token émis pour un autre ; le client nomme le serveur visé par le
paramètre `resource` (RFC 8707). Passport et `league/oauth2-server` ignorent ce paramètre. Le claim JWT standard du
destinataire, `aud` (RFC 9068), est déjà occupé chez Passport par l'identifiant du **client**, qui s'en sert pour le
retrouver (`BearerTokenValidator`, `TokenGuard::client()`).

Passport enregistre chaque token en base et la relit à chaque requête pour vérifier qu'il n'est pas révoqué.

Chez nous, la menace est faible : `/mcp` et `/api` sont la même application et exposent les mêmes use cases. Un token
MCP qui ouvrirait l'API ne donnerait aucun droit de plus.

## Décision

- **Le `resource` est validé à l'autorisation** (c'est l'une de nos ressources, sinon refus) **et affiché sur
  l'écran d'accord**.
- **Il est enregistré avec le token** (une colonne sur les tokens émis), et un token rafraîchi garde celui de son
  prédécesseur.
- **Chaque *resource server* vérifie qu'un token lui est destiné**, sinon `401` : `/mcp` comme `/api`.
- **Fermé par défaut** : un token sans destinataire n'ouvre rien. `library:token` prend la ressource en option.
- **L'API publie ses métadonnées de ressource** et répond `401` avec `WWW-Authenticate`, comme `/mcp`.

## Conséquences

- (+) Conforme à la spec MCP, et l'écart daté de l'ADR-0020 se referme.
- (+) L'utilisateur voit à quoi il donne accès, et son accord ne vaut que pour cela.
- (+) Le jour où l'API expose ce que MCP n'expose pas, la frontière existe déjà.
- (−) On étend Passport sur son parcours d'autorisation : à chaque montée de version, ce point est à revérifier.
- (−) Le destinataire ne se lit pas dans le JWT : un *resource server* hors de l'application devrait interroger la
  base, ou l'introspection. Ce n'est pas notre cas.
- (−) Le gain de sécurité est faible aujourd'hui : on paie surtout la conformité et la leçon.

## Alternatives écartées

- **Le `resource` dans `aud`** : la place standard, mais elle casse la façon dont Passport retrouve le client.
- **Un claim JWT à nous** (`resource`) : lisible sans la base, mais il faut remplacer l'entité et le repository de
  tokens de Passport, pour un gain qu'on n'utilise pas, puisque la base est lue de toute façon.
- **Une seule ressource, l'application** : la spec demande au client MCP de vérifier que le `resource` des
  métadonnées correspond à l'URL où il se connecte (`…/mcp`). Une ressource « application » ne passerait pas cette
  vérification.
- **Un scope par serveur** : un scope dit ce que le token permet, pas où il vaut, et c'est le serveur qui l'annonce
  ([ADR-0020](./0020-passport-serveur-d-autorisation-destinataire-implicite.md)).
- **Garder le destinataire implicite** : les deux serveurs étant à nous, c'est défendable sur la menace. Mais la spec
  l'exige sans condition, et l'ADR-0020 avait promis d'en sortir à ce moment précis.
