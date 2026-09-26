# 02 — Langage ubiquitaire

> Le code est en anglais ([ADR-0001](./decisions/0001-code-en-anglais-prose-en-francais.md)). Ce tableau fait
> foi : un concept, **un** mot, le même dans la doc, le code, la base… et les noms de tools MCP.

| Français | Code | Nature | Remarque |
|---|---|---|---|
| bibliothèque | `Library` | bounded context | l'ensemble des livres d'une personne |
| livre | `Book` | agrégat | un titre qu'on possède **ou** qu'on voudrait lire |
| titre | `Title` | VO | |
| auteur | `Author` | VO | un libellé, pas une entité |
| ISBN | `Isbn` | VO | normalisé en ISBN-13 |
| possédé | `owned` | état | |
| liste d'envies | *wishlist* | étagère | les livres non possédés (`owned = false`) |
| acquérir | `acquire` | comportement | liste d'envies → possédé |
| statut de lecture | `ReadingStatus` | VO (enum) | |
| à lire | `ToRead` | statut | état initial |
| en cours | `Reading` | statut | |
| lu | `Read` | statut | terminal |
| abandonné | `Abandoned` | statut | reprise possible |
| commencer la lecture | `startReading` | comportement | |
| terminer la lecture | `finishReading` | comportement | |
| abandonner | `abandon` | comportement | |
| note | `Rating` | VO | de 1 à 5 |
| noter | `rate` | comportement | |
| prêt | `Loan` | VO | le prêt **en cours** ; pas d'historique |
| emprunteur | `Borrower` | VO | texte libre |
| prêter | `lendTo` | comportement | |
| rendre (un prêt) | `markAsReturned` | comportement | côté propriétaire : « on me l'a rendu » |
| retirer (de la bibliothèque) | `RemoveBook` | use case | suppression définitive |
| étagère | *shelf* | lecture | une recherche pré-filtrée, pas un objet du domaine |

**Pièges évités**

- « Rendre » est ambigu (rendre à qui ?) : dans le code, c'est **toujours** le retour d'un prêt au
  propriétaire, `markAsReturned`.
- « Étagère » n'est **pas** un concept stocké : aucun livre n'est « rangé » quelque part, l'étagère se
  calcule. Si un jour on veut des étagères personnalisées, ce sera un nouveau concept, avec son nom.
