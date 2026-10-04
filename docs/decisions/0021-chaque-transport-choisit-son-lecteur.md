# ADR-0021 — Chaque transport choisit son lecteur, et aucun lecteur n'est servi par défaut

- **Statut** : Accepté (2026-10-04). Remplace, dans
  l'[ADR-0019](./0019-comptes-en-infrastructure-lecteur-ferme-par-defaut.md), le lecteur configuré « branché par
  défaut ».

## Contexte

L'ADR-0019 branchait `ConfiguredReader` (`LIBRARY_READER`) **par défaut**, et la route HTTP le remplaçait par le
lecteur authentifié. La route HTTP était donc fermée par défaut, mais pas le reste de l'application : une commande
artisan, un job ou une nouvelle route qui aurait eu besoin d'un lecteur aurait reçu, sans rien demander, la
bibliothèque du lecteur configuré.

La question est venue d'une autre intuition : « on ne devrait être accessible qu'en OAuth ». Elle ne tient pas pour
stdio (la spec y écarte OAuth, et la frontière de confiance y est la machine), mais elle a mis le doigt sur ce
défaut ouvert.

## Décision

- **Aucune liaison par défaut** pour `CurrentReader`.
- **`LibraryServer` choisit au démarrage**, selon son transport : `StdioTransport` → `ConfiguredReader`,
  `HttpTransport` → `AuthenticatedReader`. Un seul endroit, symétrique pour les deux transports.
- **Tout autre point d'entrée échoue** à la résolution de `CurrentReader`, jusqu'à ce qu'il choisisse
  explicitement son lecteur.
- Le middleware `ServeTheAuthenticatedReader` disparaît. `auth:api` reste sur la route : c'est lui qui vérifie le
  token.
- Les tests choisissent eux aussi : la classe de base lie `ConfiguredReader` sur un lecteur de test.

## Conséquences

- (+) Fermé par défaut partout, pas seulement sur la route HTTP. Un test vérifie que, sans choix, il n'y a pas de
  lecteur.
- (+) La règle « stdio → environnement, HTTP → token » se lit à un seul endroit.
- (−) On s'appuie sur le type du transport que le SDK passe au serveur : un transport ajouté par le SDK n'aurait
  pas de lecteur tant qu'on ne l'a pas prévu. C'est voulu : il échouerait.

## Alternatives écartées

- **Garder le lecteur configuré par défaut** : conforme à la spec, mais ouvert par défaut hors de la route HTTP.
- **Retirer stdio** pour n'avoir que la porte OAuth : on perdait ce que le projet a appris sur stdio (étapes 2, 4
  et 6), sans gain de sécurité, puisque qui lance le processus a déjà accès à tout.
- **Choisir dans un écouteur d'événement de console** (`mcp:start`) : moins visible, et il aurait fallu garder un
  middleware pour HTTP. Deux mécanismes pour une seule règle.
