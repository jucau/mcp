# ADR-0014 — Les presenters sont des services injectés

- **Statut** : Accepté (2026-09-27)

## Contexte

`BookPresenter` et `EmbeddedBooks` étaient appelés en statique (`BookPresenter::present($book)`). Matthias Noback
(*Object Design Style Guide*) distingue les **services**, qui font quelque chose et s'injectent, des objets qui
portent des données (entités, value objects, DTO). Il n'admet le statique que pour les constructeurs nommés, comme
`Rating::fromInt()`. Une méthode statique qui porte un comportement est une **dépendance cachée** : elle n'apparaît
pas dans le constructeur de celui qui l'utilise.

## Décision

- **Un presenter est un service** : `final readonly`, avec des méthodes d'instance, et **injecté par le
  constructeur** là où il sert (tools, resources, prompts).
- Ça vaut aussi pour les services qui en dépendent (`EmbeddedBooks`).
- Le statique reste réservé aux **constructeurs nommés** des value objects et des exceptions.

## Conséquences

- (+) Chaque adaptateur déclare dans son constructeur tout ce dont il dépend.
- (+) Le jour où un presenter aura besoin d'une dépendance (une locale, un générateur d'URL…), aucun appel ne
  change : il suffira de l'ajouter à son constructeur.
- (−) Un paramètre de constructeur de plus par adaptateur. Le gain n'est pas la substitution en test : les mocks
  sont interdits, et on injecte le vrai presenter, une fonction pure.

## Alternatives écartées

- **Garder le statique** parce que le presenter est pur et sans dépendance : ça marche, mais c'est une exception à
  la règle « un service s'injecte », qu'il faudrait justifier à chaque nouveau presenter.
