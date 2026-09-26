# ADR-0013 — Chaque tool annonce son `outputSchema`, et les tests vérifient qu'il le respecte

- **Statut** : Accepté (2026-09-27)

## Contexte

Nos tools renvoient un `structuredContent` sans en annoncer la forme. MCP permet de la déclarer par un
`outputSchema` ([04](../04-les-trois-primitives.md)) : le serveur **doit** alors s'y conformer, et le client
**devrait** valider. `laravel/mcp` publie le schéma mais ne valide jamais la réponse.

## Décision

- **Chaque tool qui renvoie du contenu structuré déclare un `outputSchema`** : le livre pour les tools qui
  agissent sur un livre, une page de livres pour `search_books`.
- **Le schéma d'un livre vit dans `BookPresenter::schema()`**, à côté de `present()` : un seul format par concept
  ([ADR-0008](./0008-tools-adaptateurs-minces-refus-metier.md)), avec son schéma au même endroit.
- **Les tests vérifient la conformité** : le cas nominal de chaque tool valide sa réponse réelle contre le schéma
  qu'il annonce, avec `justinrainbow/json-schema`, déclaré en dépendance de dev explicite.
- Le test de contrat de chaque tool fige aussi l'`outputSchema` à l'identique.

## Conséquences

- (+) Le client sait ce qu'il va recevoir, et le modèle sait quels champs attendre.
- (+) Une réponse qui s'écarte de ce qu'elle annonce fait échouer la porte qualité.
- (−) Une dépendance de dev de plus. Elle était déjà installée à travers Infection, mais on ne s'appuie pas sur
  une dépendance transitive.

## Alternatives écartées

- **Annoncer sans vérifier** : le schéma deviendrait une promesse que rien ne garantit.
- **Valider en production, dans `LibraryTool`** : on paierait un coût à chaque appel pour une erreur de
  programmation, que les tests suffisent à attraper.
