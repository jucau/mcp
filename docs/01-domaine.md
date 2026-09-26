# 01 — Le domaine `Library`

> Un seul bounded context, `Library`, et un seul agrégat, `Book`. On définit l'agrégat par **les invariants
> qu'il garde** ; l'état et les comportements en découlent. Les termes cités sont ceux du code, voir le
> [langage ubiquitaire](./02-langage-ubiquitaire.md).

## Mission

Tenir la **bibliothèque personnelle** d'une personne : les livres qu'elle possède ou qu'elle voudrait lire,
où elle en est de chacun, ce qu'elle en a pensé, et à qui elle les a prêtés.

## Décisions actées

| # | Question | Décision |
|---|----------|----------|
| 1 | La lecture : statut du livre ou historique ? | **Statut sur le livre** : une seule lecture par livre, pas de relecture. |
| 2 | Les livres non possédés ? | **Oui** : drapeau `owned`. Un livre non possédé forme la **liste d'envies**. |
| 3 | L'emprunteur : texte libre ou entité ? | **Texte libre** (`Borrower`). |
| 4 | Le prêt : agrégat séparé ou dans `Book` ? | **Dans `Book`**, en value object ([ADR-0007](./decisions/0007-un-agregat-book-pret-en-value-object.md)). |
| 5 | L'architecture ? | **Hexagonale ciblée** ([ADR-0006](./decisions/0006-architecture-hexagonale-ciblee.md)). |

---

## Agrégat `Book` — un livre

**Identité** : `BookId` (UUID généré par le domaine).

**État**

| Champ | Type | Remarque |
|---|---|---|
| `title` | `Title` | non vide |
| `author` | `Author` | non vide ; plusieurs auteurs tiennent dans un seul libellé (« Pratchett & Gaiman ») |
| `isbn` | `?Isbn` | optionnel ; normalisé en ISBN-13 |
| `owned` | `bool` | `false` = liste d'envies |
| `readingStatus` | `ReadingStatus` | `ToRead`, `Reading`, `Read`, `Abandoned` |
| `startedOn` | `?DateTimeImmutable` | jour où la lecture a commencé |
| `finishedOn` | `?DateTimeImmutable` | jour où elle s'est terminée (lue ou abandonnée) |
| `rating` | `?Rating` | de 1 à 5 |
| `loan` | `?Loan` | le prêt **en cours** : `borrower` + `lentOn` |

**Invariants**

- `B-1` — `title` et `author` ne sont **jamais vides** (tenu par construction des VO).
- `B-2` — un `isbn` présent est **valide** (longueur et clé de contrôle ISBN-10 ou ISBN-13), tenu par
  construction de `Isbn`.
- `B-3` — la lecture suit le **cycle de vie** ci-dessous ; toute autre transition est refusée.
- `B-4` — `finishedOn` n'est **jamais antérieur** à `startedOn`.
- `B-5` — une `rating` n'existe que sur un livre **`Read` ou `Abandoned`** : on ne note pas un livre qu'on
  n'a pas ouvert.
- `B-6` — on ne prête qu'un livre **possédé** (`loan` ⇒ `owned`).
- `B-7` — **un seul prêt en cours** : tenu par la structure (`?Loan`), pas par une garde.
- `B-8` — un livre **prêté** ne peut pas être **retiré** de la bibliothèque.

**Cycle de vie de la lecture** (`B-3`)

```
            startReading               finishReading
  ToRead ───────────────► Reading ─────────────────► Read
    │                      │  ▲
    │                      │  │ startReading (reprise : efface finishedOn et rating)
    │              abandon │  │
    │                      ▼  │
    │                    Abandoned
    │
    └──────── finishReading (lu avant d'être enregistré : pas de startedOn) ───► Read
```

- `Read` est **terminal** : pas de relecture (décision 1).
- `finishReading` depuis `ToRead` couvre le cas courant « je l'ai lu il y a des années ».

**Comportements**

| Méthode | Protège |
|---|---|
| `Book::add(id, title, author, isbn, owned)` | B-1, B-2 — commence en `ToRead`, sans prêt |
| `acquire()` | — un livre de la liste d'envies devient possédé (idempotent) |
| `startReading(on)` | B-3 |
| `finishReading(on)` | B-3, B-4 |
| `abandon(on)` | B-3, B-4 |
| `rate(Rating)` | B-5 |
| `lendTo(Borrower, on)` | B-6, B-7 |
| `markAsReturned()` | un livre non prêté ne peut pas être rendu |
| `ensureRemovable()` | B-8 — appelé par le use case `RemoveBook` avant suppression |

Les dates sont **passées** au domaine, jamais lues par lui (pas de « maintenant » caché) : c'est ce qui rend
les invariants testables.

**Erreurs de domaine** : `InvalidBookId`, `InvalidText`, `InvalidIsbn`, `InvalidRating`,
`ReadingTransitionNotAllowed`, `FinishedBeforeStarted`, `BookNotFinished`,
`BookNotOwned`, `BookAlreadyLent`, `BookNotLent`, `BookLent`, `DuplicateIsbn`, `UnknownBook`. Chacune porte
un message **compréhensible par un humain… et par un modèle** : à l'étape 3, c'est ce texte qui remontera au
LLM dans une erreur de tool.

## Invariant ensembliste : l'ISBN unique

`BOOK-UNIQ` — deux livres de la bibliothèque n'ont **jamais le même ISBN**. Un agrégat ne voit que
lui-même : il ne peut pas garantir une règle qui porte sur **l'ensemble** des livres. Elle est donc tenue
**au bord** : contrainte d'unicité en base, traduite par le repository en exception de domaine
`DuplicateIsbn`. Pas de pré-vérification applicative (elle serait sujette à une course), même pattern
que `ddd/` (ADR-0007 de Comptoir).

## Value objects

| VO | Règle |
|---|---|
| `BookId` | UUID valide |
| `Title`, `Author`, `Borrower` | un `Text` : chaîne *trimée* non vide, 255 caractères max |
| `Isbn` | accepte ISBN-10 ou ISBN-13, avec ou sans tirets ; vérifie la clé ; **normalise en ISBN-13** |
| `Rating` | entier de 1 à 5 |
| `Loan` | `Borrower` + `lentOn` |
| `ReadingStatus` | enum `to_read`, `reading`, `read`, `abandoned` |

**C'est le constructeur qui garde l'invariant**, pas le constructeur nommé. Le constructeur est le seul
passage obligé : un constructeur nommé ajouté demain ne pourra pas l'éviter, alors qu'il pourrait oublier une
validation qu'on lui aurait confiée. Le constructeur nommé se contente de **normaliser** l'entrée (*trim*,
minuscules, tirets retirés), puis il délègue. Il ne refait pas les vérifications que sa normalisation
garantit déjà : un contrôle qu'aucun chemin ne peut faire échouer est du code mort, et Infection le signale
comme un mutant qui survit.

## Cas d'usage

**Écriture** (un dossier par use case, command + handler) :

| Use case | Effet |
|---|---|
| `AddBook` | ajoute un livre (possédé ou non) ; renvoie son `BookId` |
| `AcquireBook` | la liste d'envies devient possession |
| `StartReading` / `FinishReading` / `AbandonReading` | fait avancer la lecture |
| `RateBook` | note un livre lu ou abandonné |
| `LendBook` / `ReturnBook` | prête / récupère un livre |
| `RemoveBook` | retire un livre (refusé s'il est prêté) |

**Lecture** (`Query/`) : `BookQueries` — `find(BookId)` et `search(critères)` paginé : texte libre (chaque mot
dans le titre ou l'auteur, sans tenir compte de la casse ni des accents), ISBN exact, statut de lecture, possédé ou non, prêté ou non. Les **étagères** ne sont que des recherches
pré-filtrées : « à lire », « en cours », « prêtés », « liste d'envies ».

## Ce que ça donnera en MCP (aperçu de l'étape 2)

Le domaine ne sait **rien** de MCP. L'adaptateur MCP traduira :

| Domaine | Primitive MCP | Pourquoi cette primitive |
|---|---|---|
| `BookQueries::search` | tool `search_books` (*read-only*) | le modèle décide quand chercher |
| `AddBook`, `LendBook`, `RateBook`… | tools d'écriture | le modèle agit à la demande de l'utilisateur |
| `RemoveBook` | tool *destructive* | le host peut demander confirmation |
| étagères | resources `library://shelves/...` | l'application ou l'utilisateur les joint au contexte |
| « recommande-moi ma prochaine lecture » | prompt | l'utilisateur le déclenche, il assemble étagère « à lire » et notes passées |
| erreurs de domaine | `Response::error(message)` | une erreur **métier** que le modèle peut corriger, pas une panne du protocole |
