# ADR-0037 — Le repository ne retire un livre que sur preuve

- **Statut** : Accepté (2026-10-11)

## Contexte

Un livre prêté ne peut pas être retiré (B-8). La règle vivait dans l'agrégat, `Book::ensureRemovable()`, mais le port
prenait deux identifiants : `remove(ReaderId, BookId)`. Quiconque avait ces deux valeurs pouvait retirer un livre
prêté sans jamais passer par l'agrégat. C'était au handler de penser à vérifier juste avant.

Avec un seul appelant, testé et jugé par Infection, le risque était contenu. Il serait apparu au second chemin de
suppression : une commande d'administration, un « vider ma liste d'envies ».

## Décision

- **Un objet valeur, `Removal`**, est la **preuve** qu'un livre peut être retiré. Son constructeur est privé : la
  seule façon d'en obtenir un est `Removal::of(Book)`, qui vérifie la règle.
- **Le port n'accepte que lui** : `BookRepository::remove(Removal)`.
- **Le handler n'a plus rien à ne pas oublier** : il lit le livre et passe `Removal::of($book)` au repository.

## Conséquences

- (+) Retirer un livre sans que l'agrégat ait accepté ne se compile plus : la règle est tenue par le type.
- (+) Pour retirer un livre il faut l'avoir lu, donc qu'il soit au lecteur : « retirer le livre d'un autre » n'est
  plus un cas à tester, il ne s'écrit plus.
- (−) `Book::ensureRemovable()` reste publique : PHP n'a pas de visibilité réservée à une classe amie, et `Removal`
  doit pouvoir l'appeler.
- (−) Une preuve dit qu'on a vérifié, pas que c'est encore vrai : entre la vérification et la suppression, le livre
  peut être prêté par une autre requête. C'est le verrou optimiste qui ferme cette fenêtre
  ([ADR-0035](./0035-verrou-optimiste-dans-le-repository.md)).

## Alternatives écartées

- **`remove(Book)`** : oblige à charger le livre, mais accepte encore un livre prêté.
- **`Book::remove(): Removal`** : la règle serait entièrement dans l'agrégat, mais le constructeur de `Removal`
  devrait être public pour que `Book` l'appelle, donc ouvert à tous.
- **Un drapeau « retiré » dans l'agrégat**, que `save()` traduirait en suppression : la règle et l'acte seraient
  réunis, au prix d'un `save()` qui supprime.
- **Un événement de domaine `BookRemoved`** : beaucoup de machinerie pour un projet qui n'a aucun événement.
- **« Retiré » comme état persistant** : justifié seulement si le métier voulait une corbeille.
