# ADR-0007 — Un seul agrégat `Book`, le prêt en value object

- **Statut** : Accepté (2026-09-26)

## Contexte

Le domaine manipule des livres, des lectures et des prêts. La frontière d'un agrégat est **le plus petit
ensemble d'état qui doit changer ensemble** pour garder ses invariants. Deux règles lient le prêt au livre :
on ne prête qu'un livre **possédé**, et un livre n'a **qu'un prêt en cours**. Une troisième règle en découle :
on ne retire pas un livre **prêté**.

## Décision

- Un **seul agrégat**, `Book`. La lecture (statut, dates, note) et le prêt en cours en font partie.
- Le prêt est un **value object** `Loan` (`borrower`, `lentOn`), porté par `Book` en `?Loan` : « un seul prêt
  en cours » est tenu **par la structure**, sans garde.
- Pas d'**historique** des prêts : `markAsReturned()` efface le `Loan`.
- En base, le prêt tient en deux colonnes nullables de `books` (`loan_borrower`, `lent_on`), pas de table.

## Conséquences

- (+) Toutes les règles du prêt sont vérifiées **dans une seule transaction**, par l'agrégat lui-même.
- (+) Pas d'identité à gérer pour un prêt : le modèle LLM prête « le livre X à Marie », il n'a jamais d'id de
  prêt à retrouver.
- (−) On ne peut pas répondre à « à qui ai-je prêté ce livre l'an dernier ? ». Si le besoin apparaît, le
  prêt deviendra une **entité** avec son historique : nouvel ADR.

## Alternatives écartées

- **`Loan` en agrégat séparé** : « un seul prêt en cours » et « seulement si possédé » deviendraient des
  règles **entre** agrégats, à tenir par une contrainte en base ou une coordination applicative. De la
  complexité sans contrepartie.
- **Table `loans` avec historique** : utile seulement pour un besoin qu'on n'a pas.
