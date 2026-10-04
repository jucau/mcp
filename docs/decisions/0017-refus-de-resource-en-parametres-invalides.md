# ADR-0017 — Un refus de resource est une erreur de paramètres (`-32602`), pas une erreur interne

- **Statut** : Accepté (2026-10-04). Remplace la décision de l'[ADR-0011](./0011-resources-etageres-et-livre.md)
  sur la façon dont une resource échoue.

## Contexte

L'ADR-0011 faisait passer les refus métier d'une resource par `Response::error()`. Le SDK en fait une erreur
JSON-RPC `-32603` (erreur interne) qui garde son message. On acceptait ce code comme une limite du SDK.

Deux faits ont changé la donne :

- **En HTTP** ([ADR-0016](./0016-http-sans-etat-deux-eres-origin-valide.md)), le SDK traduit `-32603` en **`500`**.
  Un id de livre inconnu ou mal formé, qui est une erreur du client, se présente alors comme une panne du serveur :
  pour un proxy, une supervision ou un client qui réessaie sur les `5xx`, c'est un mensonge.
- **La spec `2026-07-28`** donne désormais `-32602` (paramètres invalides) à une resource introuvable, au lieu de
  `-32002`.

Le SDK a un chemin pour ce code : une **`ValidationException`** levée pendant la lecture devient `-32602`, avec son
message, même sans `APP_DEBUG`, et donc un `400` en HTTP. Le client écrit à la main sur HTTP l'a fait apparaître
([05](../05-le-protocole-a-nu.md)).

## Décision

- `LibraryResource` traduit toute `DomainException` en **`ValidationException`** sur le champ `uri`. Le message
  métier reste intact.
- Un test lit un livre inconnu et un id mal formé **par HTTP**, et épingle le code `-32602` et le statut `400`.

## Conséquences

- (+) Le code suit la spec `2026-07-28`, et le statut HTTP dit la vérité : `4xx` pour une erreur du client, `5xx`
  réservé aux vraies pannes.
- (+) Rien ne change pour les tools : leurs refus restent des **résultats** `isError`
  ([ADR-0008](./0008-tools-adaptateurs-minces-refus-metier.md)).
- (−) On s'appuie sur un détail du SDK (sa traduction des `ValidationException`) plutôt que sur une API faite pour
  ça. Le test HTTP le signalera si ce détail change.
- (−) Le mot « validation » est un peu détourné : un livre inconnu n'est pas une saisie invalide au sens d'un
  formulaire. Mais c'est bien un paramètre (l'URI) qui ne désigne rien de valide.

## Alternatives écartées

- **Garder `-32603`** : acceptable en stdio, mais faux en HTTP, et contraire à la spec actuelle.
- **Lever une `JsonRpcException` avec le code voulu** : le SDK l'attrape et la ré-emballe en `-32603`.
- **Surcharger la méthode `resources/read` du SDK** : on réécrirait une partie du SDK pour un seul code d'erreur.
