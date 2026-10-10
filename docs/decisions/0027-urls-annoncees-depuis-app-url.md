# ADR-0027 — Les URL que l'application annonce viennent d'`APP_URL`, jamais de la requête

- **Statut** : Accepté (2026-10-10)

## Contexte

Les métadonnées OAuth (RFC 9728 et 8414) sont construites par `laravel/mcp` avec `url()`. Or `url()` suit l'en-tête
`Host` de la requête : `Host: evil.example` donne des métadonnées qui désignent `http://evil.example` comme serveur
d'autorisation ([09](../09-api-rest.md)). Un client ne choisit pas le `Host` qu'il envoie, mais un cache placé devant
le serveur pourrait garder et resservir une telle réponse à tous.

L'[ADR-0026](./0026-un-destinataire-explicite-par-token.md) compare le `resource` d'une demande à l'URI de chacune de
nos ressources : cette URI doit être **une** valeur, la même quelle que soit l'adresse par laquelle on est arrivé.

Laravel propose un middleware `TrustHosts`, qui refuse les `Host` inconnus. Il est **inactif** en environnement
`local` et pendant les tests.

## Décision

- **La racine des URL est forcée sur `APP_URL`** (`URL::forceRootUrl`) : toute URL générée par l'application
  (métadonnées, redirections, formulaires) en découle, quel que soit le `Host` reçu.
- **Chaque ressource protégée a une URI canonique**, dérivée de cette racine (`…/mcp`). Le `resource` d'une demande
  doit lui être **égal**, à la chaîne près : `localhost` et `127.0.0.1` sont deux ressources différentes.
- **Les clients du projet se configurent sur `APP_URL`** (`.mcp.json`, client écrit à la main).

## Conséquences

- (+) Les métadonnées ne se laissent plus dicter par la requête, en local comme en production.
- (+) Une seule URI par ressource : la comparaison de l'ADR-0026 est une égalité, sans interprétation.
- (−) L'application ne répond correctement qu'à une seule adresse : y accéder par `127.0.0.1` donne des URL en
  `localhost`. C'est voulu.
- (−) Une requête avec un `Host` étranger est encore servie, avec les bonnes URL. La refuser serait le rôle de
  `TrustHosts` en production.

## Alternatives écartées

- **`TrustHosts` seul** : inactif en local et dans les tests, il ne protégerait ni l'environnement où l'on observe
  le protocole, ni les tests qui doivent le prouver.
- **Corriger les métadonnées seulement** (surcharger les routes de `laravel/mcp`) : les autres URL générées
  resteraient dictées par la requête.
- **Normaliser les adresses** (considérer `127.0.0.1` et `localhost` comme une seule) : le serveur interpréterait
  ce que la RFC 8707 compare.
