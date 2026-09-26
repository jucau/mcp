# ADR-0012 — Les prompts embarquent les données de la bibliothèque

- **Statut** : Accepté (2026-09-27)

## Contexte

Un prompt renvoie des messages que le host insère dans la conversation
([04](../04-les-trois-primitives.md)). Pour `recommend_next_read` et `reading_review`, le modèle a besoin des
livres concernés. Il y a trois façons de les lui donner.

## Décision

- **Le serveur lit la bibliothèque et embarque les livres en JSON dans le texte du message**, au format de
  `BookPresenter`, entre une consigne et une question.
- **Les messages ont le rôle `user`** : c'est l'utilisateur qui demande, le prompt formule sa demande à sa place.
- **Noms en `snake_case`**, comme les tools (`recommend_next_read`, `reading_review`).
- **Les arguments sont validés par le prompt** (`$request->validate()`) : le protocole ne transporte que des
  chaînes. Une erreur est une erreur de protocole (`-32602`).
- **Lire tous les livres d'une recherche** passe par `BookQueries::all()`, qui sert aussi les étagères.

## Conséquences

- (+) Le prompt fonctionne sur n'importe quel host : il ne suppose ni le support des `resource_link`, ni un
  modèle qui pense à appeler un tool.
- (+) Le prompt est testable de bout en bout : son texte exact dépend de la bibliothèque, et un test le vérifie.
- (−) Le contexte grossit avec la bibliothèque. Pour quelques centaines de livres, c'est acceptable.
- (−) Le contenu est figé au moment du `prompts/get` : si la bibliothèque change pendant la conversation, le
  modèle ne le voit pas.

## Alternatives écartées

- **Des `resource_link` vers les étagères** : plus léger, mais ça dépend du host. Et le SDK ne sait pas produire de
  resource **embarquée** (le type `resource` de la spec), seulement des liens.
- **Une consigne seule** (« utilise `search_books` ») : le prompt n'apporterait presque rien de plus qu'une phrase
  tapée par l'utilisateur.
