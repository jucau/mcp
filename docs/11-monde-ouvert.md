# 11 — Sortir du monde fermé : un tool appuyé sur un catalogue ouvert

> Étape 11 de la [roadmap](./roadmap.md). Jusqu'ici, tout ce que le serveur lisait ou écrivait vivait dans **sa**
> base. `lookup_book` est le premier tool qui en sort : il interroge un catalogue public. L'annotation qui le dit,
> `openWorldHint`, tient en une ligne. Ce qui s'apprend, c'est tout ce que cette ligne entraîne.

## Ce que dit `openWorldHint`

Le hint ([04](./04-les-trois-primitives.md)) répond à une question : le tool agit-il sur un **monde fermé**, dont le
serveur maîtrise le contenu, ou sur un **monde ouvert**, fait de services et de contenus que personne ici n'a écrits ?

Nos dix premiers tools déclarent `#[IsOpenWorld(false)]` : ils ne voient que la bibliothèque du lecteur. Pour eux,
tout ce qui revient au modèle a été saisi par le lecteur, puis validé par le domaine.

`lookup_book` déclare `#[IsOpenWorld]`. Ce qu'il renvoie vient d'[Open Library](https://openlibrary.org), un
catalogue que n'importe qui peut modifier. La conséquence pour le host est une question de **confiance** : un titre
de livre renvoyé par un catalogue public est un texte arbitraire, qui peut contenir des instructions adressées au
modèle. Le hint ne protège de rien : il **prévient** le host, qui décide de son UX.

## Le tool

```
lookup_book(isbn) → { isbn, title, author, published_in, pages }
```

| Hint | Valeur | Pourquoi |
|---|---|---|
| `readOnlyHint` | `true` | il ne change rien, ni chez nous ni dans le catalogue |
| `destructiveHint` | `false` | |
| `idempotentHint` | `true` | le rappeler n'a aucun effet de plus |
| `openWorldHint` | **`true`** | il interroge un service tiers |

Le modèle l'enchaîne seul avec `add_book` : « ajoute le 9782070360024 » devient `lookup_book`, puis `add_book` avec
le titre et l'auteur trouvés. Les instructions du serveur le lui disent.

## Pourquoi un tool séparé, et pas un `add_book` qui se complète seul

On aurait pu laisser `add_book` aller chercher le titre quand on ne lui donne qu'un ISBN
([ADR-0034](./decisions/0034-catalogue-ouvert-derriere-un-port.md)). Trois raisons de ne pas le faire :

- **Un appel réseau caché dans une écriture.** Un `add_book` qui échoue parce que le catalogue est en panne, c'est
  un refus que le domaine ne sait pas expliquer.
- **Deux profils mélangés.** Le tool serait à la fois une écriture et un monde ouvert : le host ne pourrait plus
  distinguer « lit le web » de « modifie ta bibliothèque ».
- **Une décision retirée au lecteur.** Ni lui ni le modèle ne verraient ce qui a été trouvé avant que ce soit
  enregistré.

Un tool séparé respecte aussi la règle « un tool = un use case »
([ADR-0008](./decisions/0008-tools-adaptateurs-minces-refus-metier.md)).

## Le premier port sortant qui ne soit pas la base

```
Application/Book/
├── Catalog/
│   ├── BookCatalog.php          le port : find(Isbn): ?CatalogEntry
│   ├── CatalogEntry.php         ce qu'on garde d'une fiche : isbn, title, author, publishedIn, pages
│   ├── NotInCatalog.php         le catalogue ne connaît pas cet ISBN
│   └── CatalogUnavailable.php   le catalogue ne répond pas
└── LookUpBook/                  le use case : valider l'ISBN, interroger le port
Infrastructure/Catalog/
└── OpenLibraryBookCatalog.php   l'adaptateur HTTP
```

Le use case fait deux choses avant de toucher au port : il **valide l'ISBN** avec l'objet valeur du domaine, et il
le **normalise en ISBN-13**. Un ISBN faux n'atteint donc jamais le réseau, et le catalogue reçoit toujours la même
forme, que le lecteur ait tapé un ISBN-10, des tirets ou des espaces.

Le même use case sert les **trois** portes d'entrée : le tool MCP, `GET /api/catalog/{isbn}`, et le formulaire
d'ajout du front, qui se complète depuis l'ISBN.

## Ce qui peut mal tourner, et ce qu'on en dit

Un monde ouvert apporte des échecs que la base ne connaissait pas :

| Situation | Dans le cœur | Tool MCP | API REST |
|---|---|---|---|
| ISBN mal formé | `InvalidIsbn` (domaine), avant tout appel | `isError`, le message du domaine | `422` `invalid-isbn` |
| ISBN inconnu du catalogue | `NotInCatalog` | `isError` | `404` `not-in-catalog` |
| Délai dépassé, panne, quota, réponse illisible | `CatalogUnavailable` | `isError` | `503` `catalog-unavailable` |

**Les deux derniers ne sont pas des règles du domaine** : aucune règle de la bibliothèque n'est en jeu. Ce sont des
exceptions de la couche application, qui appartiennent au contrat du port. Ce sont pourtant des **refus**, au sens de
l'interface `Refusal` ([ADR-0036](./decisions/0036-un-refus-est-un-type-du-projet.md)) : une réponse écrite pour
l'appelant. Les adaptateurs les traduisent donc comme n'importe quel refus, et ils atteignent le modèle en production
comme en dev : sans titre ni auteur, il doit savoir qu'il lui faut les demander au lecteur.

**Tout échec du catalogue devient `CatalogUnavailable`**, qu'il s'agisse d'un délai dépassé, d'un `500`, d'un `429`
ou d'un corps qui n'est pas du JSON. Vu de l'appelant, la conséquence est la même : pas de fiche maintenant. Le
détail ne sort pas de l'adaptateur.

## Un contenu que personne ici n'a écrit

L'adaptateur est une **couche anti-corruption** : il ne laisse passer qu'une fiche typée et bornée, jamais le JSON
du catalogue.

- Il ne garde que **cinq champs**. Sujets, éditeurs, liens, notes : rien de tout cela ne remonte au modèle.
- Le titre et l'auteur sont ramenés sur **une ligne** et coupés à **255 caractères**, la limite du domaine : une
  fiche trouvée peut toujours être passée telle quelle à `add_book`.
- Une fiche **sans titre** est traitée comme inconnue : elle ne permettrait d'ajouter aucun livre.
- Plusieurs auteurs sont joints en un seul texte (« Terry Pratchett & Neil Gaiman »), la forme qu'attend `add_book`.
- L'année est extraite d'une date au format libre (`"1984"`, `"07-01-1972"`) ; sans année lisible, elle vaut `null`.

L'`outputSchema` du tool annonce cette forme, et le test du tool vérifie que la réponse la respecte
([ADR-0013](./decisions/0013-output-schema-verifie-par-les-tests.md)).

**Ce que ça ne fait pas** : un titre de 255 caractères peut encore contenir une phrase écrite pour tromper le
modèle. Borner la forme réduit la surface, pas le risque. C'est précisément ce que `openWorldHint` annonce au host.

## Tester sans réseau

- **Le use case** se teste avec `InMemoryBookCatalog`, une doublure d'état. On lui dit quelles fiches il connaît,
  ou qu'il est en panne, et il **note les ISBN qu'on lui demande**. C'est ce relevé qui prouve qu'un ISBN mal formé
  n'atteint jamais le catalogue, et qu'un ISBN-10 y arrive en ISBN-13.
- **Un trait de contrat** (`BookCatalogContract`) est joué par la doublure **et** par l'adaptateur réel : une fiche
  connue revient entière, un ISBN inconnu donne `null`, une panne lève `CatalogUnavailable`.
- **L'adaptateur HTTP** a ses tests Feature, contre des réponses enregistrées d'Open Library : une fiche complète,
  une fiche sans auteur, une date au format libre, un `500`, un délai dépassé, un corps illisible.
- **Les tests des tools et de l'API** branchent la doublure sur le port : ils vérifient le câblage, pas le catalogue.
- **Aucun test ne sort sur le réseau** : `Http::preventStrayRequests()` fait échouer toute requête que rien n'a
  prévue.

## Travailler sans réseau

Dépendre d'un service tiers, c'est aussi ne plus pouvoir travailler dans un train. `LIBRARY_CATALOG` choisit
l'adaptateur branché sur le port :

| Valeur | Adaptateur | |
|---|---|---|
| `open-library` (défaut) | `OpenLibraryBookCatalog` | le vrai catalogue |
| `fake` | `FakeBookCatalog` | aucune requête : la fiche est **calculée depuis l'ISBN** |

Le faux catalogue répond à tout ISBN valide par une fiche **visiblement fausse** (« Fake book 9782070360024 », de
« Fake Author ») : ajoutée à une bibliothèque de dev, elle ne peut pas passer pour un vrai livre. Elle est
**déterministe** : le même ISBN donne toujours la même fiche, sans quoi le tool mentirait sur son `idempotentHint`.

Deux règles simulent le reste du comportement réel, pour que les trois issues se voient hors ligne :

| L'ISBN-13 se termine par | Issue | Exemple |
|---|---|---|
| `0` | inconnu du catalogue (`NotInCatalog`) | `9780301000060` |
| `9` | catalogue indisponible (`CatalogUnavailable`) | `9780441172719` |
| autre chose | une fiche | `9782070360024` |

**Ce n'est pas la doublure des tests.** `InMemoryBookCatalog` vit dans `tests/` et se programme : on lui dit quelles
fiches il connaît, on lui fait noter les appels. `FakeBookCatalog` vit dans `app/`, ne se programme pas, et ne sert
aucun test que le sien. Il ne joue d'ailleurs pas le trait de contrat, qui demande de faire connaître au catalogue
une fiche arbitraire.

Une valeur inconnue de `LIBRARY_CATALOG`, ou `fake` en production, **fait échouer le démarrage** : une faute de
frappe ne retombe pas en silence sur le réseau, et le faux ne sert jamais de vraies données.

## Ce qu'on laisse de côté

- **Le cache.** Chaque appel interroge le catalogue. Une fiche change rarement : un cache par ISBN serait la
  première chose à ajouter si l'usage grandissait.
- **Les nouvelles tentatives.** Un échec est rendu tel quel : c'est le modèle, ou le lecteur, qui décide de réessayer.
- **L'effet du hint dans le host.** La spec laisse le host libre de ce qu'il en fait, et rien ne garantit qu'il en
  fasse quelque chose de visible.
