# ADR-0018 — Un lecteur par livre, et aucun compte dans le domaine

- **Statut** : Accepté (2026-10-04)

## Contexte

L'étape 5 ouvre le serveur à plusieurs personnes, chacune avec sa bibliothèque. Il faut décider où vit la notion
de personne. Côté infrastructure, Laravel et Passport apportent un modèle `User` : un compte, un mot de passe, des
tokens. La tentation est de le faire entrer dans le domaine, ou au moins son identifiant (`UserId`).

Le domaine, lui, n'a besoin que d'une chose : savoir **à qui est** chaque livre, pour ne montrer et ne modifier
que les livres de celui qui demande. Aucune de ses règles ne porte sur la personne elle-même.

## Décision

- **Le domaine connaît le lecteur, pas l'utilisateur.** `Book` porte un `ReaderId` (value object, UUID), fixé à
  l'ajout et immuable (`B-9`). Il n'y a pas d'entité `Reader` : aucune règle ne la justifierait.
- **Le mot est « lecteur »** (`Reader`), déjà présent dans notre langage (« le jour du lecteur »,
  [ADR-0010](./0010-instants-en-utc-jours-du-lecteur.md)). « User » est le mot de l'authentification ;
  « owner » se confondrait avec `owned`.
- **Le domaine ne génère pas les `ReaderId`** : il les reçoit. C'est l'infrastructure qui crée les comptes.
- **Chaque use case agit pour un lecteur** : commandes et requêtes portent le `ReaderId`.
- **Le cloisonnement est tenu par les ports** : `BookRepository` et `BookQueries` prennent le `ReaderId` et ne
  voient que ses livres. Un livre d'un autre lecteur est **inconnu** (`UnknownBook`), jamais « interdit ».
- **`BOOK-UNIQ` devient par lecteur** : unicité de (lecteur, ISBN).
- **Tout ce qui concerne le compte vit en infrastructure** : le modèle `User`, la connexion, Passport, et la
  traduction du compte authentifié en `ReaderId`, par le token en HTTP, par l'environnement en stdio (la spec MCP
  demande de ne pas faire d'OAuth en stdio).

## Conséquences

- (+) Le domaine et l'application restent testables sans aucune notion d'authentification : un `ReaderId` suffit.
- (+) Le cloisonnement ne dépend pas de la vigilance de chaque handler : un port qui exige le lecteur ne peut pas
  l'oublier. Le trait de contrat le vérifie pour le double in-memory comme pour Eloquent.
- (+) Changer de mécanisme d'authentification ne touche ni le domaine ni l'application.
- (−) Chaque signature de use case et de port s'alourdit d'un paramètre.
- (−) Le fuseau horaire reste global (`library.reader_timezone`) dans un premier temps, alors qu'il appartient à
  chaque lecteur ([ADR-0010](./0010-instants-en-utc-jours-du-lecteur.md)). Il deviendra une donnée du compte, en
  infrastructure, quand l'authentification existera.

## Alternatives écartées

- **Un `UserId` dans le domaine** : il y ferait entrer le vocabulaire de l'authentification.
- **Une entité `Reader` dans le domaine** : aucune règle ne la justifie, ce serait une coquille vide.
- **Un contrôle d'appartenance dans chaque handler** (charger le livre, puis vérifier son lecteur) : dix handlers,
  dix occasions d'oublier, et un oubli est une fuite de données. Le port qui exige le lecteur rend l'oubli
  impossible.
- **Répondre « interdit » pour le livre d'un autre** : ce serait confirmer qu'il existe.
