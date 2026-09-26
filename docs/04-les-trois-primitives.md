# 04 — Les trois primitives

> Étape 3 de la [roadmap](./roadmap.md). Les tools couvrent maintenant tous les use cases, et chacun dit au
> client **ce qu'il fait au monde** par ses annotations. Les resources et les prompts viendront compléter ce
> document.

## Qui contrôle quoi

| Primitive | Contrôlée par | Dans Marque-page |
|---|---|---|
| **Tool** | le **modèle**, qui décide de l'appeler | `search_books`, `add_book`, `remove_book`… |
| **Resource** | l'**application** (le host), qui décide quoi mettre en contexte | à venir : `library://books/{id}` |
| **Prompt** | l'**utilisateur**, qui le choisit explicitement | à venir : `recommend-next-read` |

## Les annotations d'un tool

Un tool annoncé par `tools/list` peut porter quatre **hints** :

| Hint | Question | Défaut de la spec |
|---|---|---|
| `readOnlyHint` | Le tool modifie-t-il quoi que ce soit ? | `false` : il peut modifier |
| `destructiveHint` | S'il modifie, peut-il **détruire** ou écraser ? | `true` : il peut détruire |
| `idempotentHint` | Le rappeler avec les mêmes arguments a-t-il un effet **supplémentaire** ? | `false` : oui |
| `openWorldHint` | Touche-t-il un monde **extérieur** (web, API tierce) ou un domaine fermé ? | `true` : extérieur |

**Les défauts sont pessimistes, exprès.** Un tool qui ne dit rien est supposé capable du pire : modifier,
détruire, cumuler ses effets, sortir du système. Un client prudent demandera donc confirmation. Conséquence
directe : nos deux premiers tools, qui annonçaient `"annotations": {}`, se présentaient comme **destructifs** —
`search_books` compris. D'où la règle : **chaque tool déclare ses quatre hints**
([ADR-0009](./decisions/0009-quatre-annotations-explicites-par-tool.md)).

**Un hint n'est pas une garantie.** La spec le dit : un client ne doit pas s'y fier venant d'un serveur qu'il ne
connaît pas. Le hint sert au host (l'application : Claude Code, Claude Desktop) pour décider de son UX, par
exemple demander ou non confirmation. Il ne remplace ni une autorisation, ni une règle métier.

**Annotation et description ne parlent pas à la même personne.** L'annotation s'adresse au **host**, le
logiciel. La description s'adresse au **modèle**. Un tool destructif le dit donc aux deux : `destructiveHint` pour
que le host protège l'utilisateur, et « only when the user explicitly asks » pour que le modèle ne l'appelle pas
de sa propre initiative.

## `remove_book` : retirer pour de bon

| Hint | Valeur | Pourquoi |
|---|---|---|
| `readOnlyHint` | `false` | il retire un livre |
| `destructiveHint` | `true` | le livre disparaît, avec son historique de lecture, sa note et ses dates |
| `idempotentHint` | `true` | un deuxième appel ne retire rien de plus : le livre n'est déjà plus là |
| `openWorldHint` | `false` | il ne touche que la bibliothèque de l'utilisateur |

**Idempotent ne veut pas dire « même réponse ».** Le deuxième appel est refusé (« No book with id … ») alors que
le premier a réussi. La spec définit l'idempotence par l'**effet sur l'environnement**, pas par la réponse : après
un ou deux appels, la bibliothèque est dans le même état. C'est ce qui compte pour un client qui voudrait
**rejouer** un appel après une coupure réseau.

**Il renvoie le livre retiré**, tel qu'il était. Le modèle peut dire à l'utilisateur exactement ce qui est parti,
sans s'appuyer sur ses souvenirs de la conversation. Un `add_book` recréerait un livre **neuf** (nouvel id, sans
historique) : la suppression n'est pas vraiment réversible.

**Refusé tant que le livre est prêté** : c'est une règle du domaine (`Book::ensureRemovable()`). Le tool ne fait
que transmettre le refus ([ADR-0008](./decisions/0008-tools-adaptateurs-minces-refus-metier.md)).

**Pas de données de démo** : retirer un livre ne crée aucun état nouveau à montrer.

## `start_reading`, `finish_reading`, `abandon_reading` : le parcours de lecture

Les trois tools font avancer le statut de lecture. Les transitions permises sont des règles du domaine
([01](./01-domaine.md)) : commencer un livre *à lire* ou *abandonné*, finir un livre *à lire* ou *en cours*,
abandonner un livre *en cours*. Un livre déjà *lu* ne se recommence pas : la relecture n'est pas modélisée.

| Hint | `start_reading` | `finish_reading` | `abandon_reading` |
|---|---|---|---|
| `readOnlyHint` | `false` | `false` | `false` |
| `destructiveHint` | **`true`** | `false` | `false` |
| `idempotentHint` | `true` | `true` | `true` |
| `openWorldHint` | `false` | `false` | `false` |

**Pourquoi `start_reading` est destructif.** Reprendre un livre abandonné efface sa date de fin et sa **note** :
une nouvelle lecture commence, l'ancienne disparaît. Sur un livre *à lire*, il n'efface rien. Mais un hint dit
ce que le tool **peut** faire, pas ce qu'il fait à chaque appel. Pour les deux autres, la date de fin qu'ils
écrivent était vide avant, dans tous les cas permis : ils ajoutent sans rien écraser.

**Pourquoi les trois sont idempotents.** Un deuxième appel trouve le livre déjà dans le statut visé, que le
domaine refuse de quitter par la même transition (« Cannot finish reading "Dune": its reading status is read. »).
La bibliothèque ne bouge pas.

**La date, c'est le serveur qui la connaît.** Chaque tool accepte un `on` optionnel (`YYYY-MM-DD`) pour une
lecture passée (« j'ai fini Dune la semaine dernière »). Sans `on`, c'est l'horloge du serveur qui donne
« aujourd'hui » (`Clock`). Un modèle ne connaît pas la date de façon fiable : sa description lui interdit de la
deviner. Même principe que pour l'ISBN et l'id : **ce que le modèle ne sait pas, il ne l'invente pas.**
« Aujourd'hui » est le jour **du lecteur**, dans son fuseau, pas en UTC
([ADR-0010](./decisions/0010-instants-en-utc-jours-du-lecteur.md)).

**Ils renvoient le livre à jour**, comme `add_book` : le modèle voit le nouveau statut et les dates, sans avoir à
relancer une recherche.

## `rate_book` : écraser, c'est détruire

| Hint | Valeur | Pourquoi |
|---|---|---|
| `readOnlyHint` | `false` | il enregistre une note |
| `destructiveHint` | `true` | noter à nouveau **remplace** l'ancienne note |
| `idempotentHint` | `true` | la même note deux fois donne le même état |
| `openWorldHint` | `false` | il ne touche que la bibliothèque |

**La spec définit « destructif » par son contraire.** `destructiveHint: false` veut dire « uniquement des mises
à jour **additives** ». Notre critère : un tool est destructif s'il peut **effacer une information enregistrée**
(une note, une date, un prêt) que rien ne permet de retrouver. Remplacer une note de 3 par un 4 efface le 3 :
`rate_book` est destructif. En revanche, un **état qui avance** ne l'est pas : quand `finish_reading` passe le
statut de `reading` à `read`, il ne perd rien de ce que l'utilisateur a saisi, et la date de fin qu'il écrit
était vide avant.

**La règle reste dans le domaine.** Le schéma annonce les bornes (`minimum: 1`, `maximum: 5`, lues sur
`Rating::MIN` et `Rating::MAX`) pour que le modèle ne se trompe pas. Mais c'est le value object `Rating` qui
refuse une note hors bornes, et `Book::rate()` qui refuse de noter un livre ni lu ni abandonné. Le tool
transmet ces refus sans les dupliquer.

**Une note vient de l'utilisateur.** « J'ai adoré » n'est pas un 5 : la description demande au modèle de ne
jamais déduire une note d'une opinion. Même principe que pour l'ISBN, l'id et la date.

## `lend_book` et `return_book` : un hint qui révèle le modèle

| Hint | `lend_book` | `return_book` |
|---|---|---|
| `readOnlyHint` | `false` | `false` |
| `destructiveHint` | `false` | **`true`** |
| `idempotentHint` | `true` | `true` |
| `openWorldHint` | `false` | `false` |

**`lend_book` ajoute, `return_book` efface.** Prêter pose un prêt sur un livre qui n'en avait pas : le domaine
refuse un second prêt (« already lent »). C'est donc un ajout. Rendre, au contraire, **efface** le prêt :
l'emprunteur et la date disparaissent, et rien n'en garde la trace. D'après la définition de la spec, c'est
destructif.

**Le hint révèle une limite du modèle de données.** Qu'un geste aussi banal que « Marie m'a rendu 1984 » soit
destructif, c'est le signe que la bibliothèque **n'a pas d'historique des prêts** : le prêt est un value object
remplacé, pas un événement conservé ([ADR-0007](./decisions/0007-un-agregat-book-pret-en-value-object.md)).
C'est un choix assumé pour garder le domaine petit. Avec un historique, `return_book` deviendrait un ajout
(« rendu le … »), et son hint passerait à `false`. Bien choisir les annotations oblige à se demander ce que
chaque tool fait **vraiment** aux données.

**Les deux sont idempotents** : au deuxième appel, le domaine refuse (« already lent », « nothing to return ») et
la bibliothèque ne change pas.

**L'emprunteur est un nom libre**, tel que l'utilisateur l'a donné : il n'existe pas de fiche « contact ».
`lend_book` accepte aussi le `on` optionnel des tools de lecture, et refuse de prêter un livre de la liste
d'envies. Ce refus vient lui aussi du domaine.

## `acquire_book` : l'idempotence la plus simple

| Hint | Valeur | Pourquoi |
|---|---|---|
| `readOnlyHint` | `false` | le livre passe de la liste d'envies aux livres possédés |
| `destructiveHint` | `false` | un état qui avance, rien d'effacé |
| `idempotentHint` | `true` | acquérir un livre déjà possédé ne change rien |
| `openWorldHint` | `false` | il ne touche que la bibliothèque |

**Idempotent sans refus.** Les autres tools idempotents le sont parce que le domaine **refuse** le deuxième appel.
`acquire_book` l'est au sens le plus simple : `Book::acquire()` n'a pas de garde, donc acquérir un livre déjà
possédé réussit et renvoie le même livre. Les deux formes respectent la spec, qui ne regarde que l'état de la
bibliothèque. Elles diffèrent pour le modèle : un refus lui apprend qu'il s'est trompé, une réussite silencieuse
ne lui apprend rien. Ici c'est sans conséquence : le résultat voulu, « je possède ce livre », est atteint.

**Acquérir n'est pas ajouter.** `acquire_book` concerne un livre **déjà** dans la liste d'envies. Pour un livre
possédé qui n'est pas encore dans la bibliothèque, c'est `add_book`. La description le dit, pour que le modèle ne
cherche pas un id qui n'existe pas.
