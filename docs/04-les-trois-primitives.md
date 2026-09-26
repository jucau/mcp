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

## `remove_book` : le seul tool destructif

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
