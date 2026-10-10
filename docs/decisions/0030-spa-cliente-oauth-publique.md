# ADR-0030 — Le frontend est une SPA cliente OAuth publique, et le token reste en mémoire

- **Statut** : Accepté (2026-10-10)

## Contexte

Le frontend doit obtenir un token pour l'API. Le brouillon de l'IETF *OAuth 2.0 for Browser-Based Apps* recommande
d'abord un **BFF** (*Backend For Frontend*) : un serveur fait l'OAuth et le navigateur n'a qu'un cookie de session.
Mais l'objet de l'étape est de voir le parcours OAuth depuis un client, comme Claude Code le fait pour `/mcp`
([ADR-0026](./0026-un-destinataire-explicite-par-token.md)). Avec un BFF, le navigateur ne ferait plus d'OAuth.

## Décision

- **Une SPA cliente publique** : code d'autorisation, PKCE (`S256`), `resource` qui nomme l'API, par `oauth4webapi`.
- **La découverte part du `401`** de l'API : métadonnées de la ressource, puis du serveur d'autorisation. Le front ne
  connaît que l'URL de l'API et son `client_id`.
- **Un client pré-enregistré**, public, avec une seule `redirect_uri`, créé par le seeder. Pas d'enregistrement
  dynamique depuis un navigateur.
- **Le token et le refresh token restent en mémoire.** Le `code_verifier` et le `state` passent par
  `sessionStorage`, le temps de la redirection.
- **Un rechargement relance l'autorisation**, que Passport accorde sans écran tant que le consentement est actif.
- **CORS** ouvre `/api`, `/oauth/token` et les métadonnées à la seule origine du front, et expose `WWW-Authenticate`
  et `Location`.
- **Cette décision tombe** avant toute mise en production : un BFF y serait la règle.

## Conséquences

- (+) Le parcours OAuth complet est observable depuis le navigateur.
- (+) Aucun token dans un stockage que n'importe quel script de la page peut lire.
- (−) Un script injecté dans la page pourrait encore agir avec le token pendant la session : c'est le risque que le
  BFF supprime.
- (−) Chaque rechargement coûte une redirection.

## Alternatives écartées

- **Un BFF** : la meilleure pratique en production, mais le navigateur n'y fait pas d'OAuth.
- **Le token dans `localStorage`** : il survivrait au rechargement, mais n'importe quel script de la page pourrait le
  lire.
- **L'enregistrement dynamique** (DCR) depuis le navigateur : un client par visite, et un mécanisme que la spec MCP
  déprécie.
- **Une session Laravel (Sanctum)** : simple, mais ce n'est plus OAuth, et l'API n'aurait plus un seul mode
  d'authentification.
