# 04 — Les trois primitives

> Étape 3 de la [roadmap](./roadmap.md). Les tools couvrent maintenant tous les use cases, et chacun dit au
> client **ce qu'il fait au monde** par ses annotations. Les resources exposent les étagères et les livres en
> lecture. Les prompts proposent deux conversations prêtes à l'emploi, que l'utilisateur lance lui-même.

## Qui contrôle quoi

| Primitive | Contrôlée par | Dans Marque-page |
|---|---|---|
| **Tool** | le **modèle**, qui décide de l'appeler | `search_books`, `add_book`, `remove_book`… |
| **Resource** | l'**application** (le host), qui décide quoi mettre en contexte | `library://shelves/reading`, `library://books/{id}` |
| **Prompt** | l'**utilisateur**, qui le choisit explicitement | `recommend_next_read`, `reading_review` |

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

## Les resources : des données, pas des actions

Un tool est une **action** que le modèle décide d'appeler. Une resource est une **donnée**, adressée par une URI,
que l'**application** (le host) choisit de mettre dans le contexte du modèle.

**« Contrôlée par l'application » veut dire que le host choisit sa politique.** Le protocole ne dit pas qui
déclenche la lecture. Claude Code en applique deux :

- **l'utilisateur mentionne une resource** comme un fichier (`@library:library://shelves/reading`) : son contenu
  est joint au message, et le modèle n'a rien eu à décider ;
- **le modèle lit une resource lui-même**, avec des tools que Claude Code lui fournit (`ListMcpResourcesTool`,
  `ReadMcpResourceTool`). Ces tools appartiennent au host, pas à notre serveur.

Un autre host pourrait n'en appliquer aucune, ou demander une confirmation avant chaque lecture.

| Méthode | Ce qu'elle renvoie |
|---|---|
| `resources/list` | les resources à **URI fixe** : ce que le host peut proposer d'emblée |
| `resources/templates/list` | les **templates d'URI** (RFC 6570), comme `library://books/{id}` : une famille de resources |
| `resources/read` | le **contenu** d'une URI : du texte ou du binaire, avec son `mimeType` |

**Quand choisir une resource plutôt qu'un tool ?** Quand la donnée se lit **telle quelle** et se désigne par un
nom stable. « Mes lectures en cours » est une resource : l'utilisateur la joint à sa question. « Les livres de
Tolkien que j'ai prêtés » est une recherche avec des critères libres, donc un tool (`search_books`). Les deux
exposent la même lecture (`BookQueries`) : une resource n'est qu'un autre **adaptateur primaire**.

### Les étagères : quatre URI fixes

| URI | Contenu |
|---|---|
| `library://shelves/to-read` | les livres possédés, pas encore commencés, **et à la maison** : un livre prêté ne se choisit pas comme prochaine lecture |
| `library://shelves/reading` | les lectures en cours |
| `library://shelves/lent` | les livres prêtés, avec l'emprunteur et la date |
| `library://shelves/wishlist` | la liste d'envies |

Une étagère n'est qu'une **recherche pré-filtrée** ([02](./02-langage-ubiquitaire.md)), définie une seule fois
dans l'enum `Shelf`, que les resources et le prompt `recommend_next_read` partagent. Chaque resource renvoie
**tous** les livres de l'étagère (`BookQueries::all()`), sans pagination : une resource se lit d'un
bloc, et la spec ne prévoit pas de paginer son contenu. Le contenu est du JSON (`application/json`), avec le même
format de livre que les tools (`BookPresenter`).

### Le livre : un template d'URI

`library://books/{id}` désigne n'importe quel livre. Il n'apparaît pas dans `resources/list` (il y en aurait un
par livre) mais dans `resources/templates/list`. Le client remplace `{id}` par un id, et le SDK passe la variable
à la resource comme un argument.

**Dans Claude Code, un template sert au modèle, pas à l'utilisateur.** Observé à l'usage :

- l'autocomplétion de `@library:` ne propose que les URI de `resources/list`, jamais les templates ;
- une mention tapée à la main avec une URI valide (`@library:library://books/758537fa-…`) **n'est pas jointe** :
  seul le texte arrive, et le modèle doit lire la resource lui-même ;
- `library://books/` sans id n'est reconnu par aucune resource : « Resource not found ».

Les étagères, à URI fixe, servent donc à l'**utilisateur**, qui les joint à sa question. Le livre, par template,
sert au **modèle** : il obtient l'id par `search_books` et lit la resource avec son propre tool. C'est ce qui
justifie les étagères en URI fixes plutôt qu'en template `library://shelves/{shelf}`
([ADR-0011](./decisions/0011-resources-etageres-et-livre.md)).

**La complétion d'arguments** (`completion/complete`) comblerait ce manque : le client demande au serveur des
valeurs pour `{id}` à partir de ce que l'utilisateur tape (« dun » → Dune). Elle est dans « Plus tard » de la
[roadmap](./roadmap.md). Il faudra aussi vérifier qu'un host l'utilise pour les templates.

### Les erreurs d'une resource sont des erreurs de protocole

C'est la grande différence avec les tools. Un tool en échec renvoie un **résultat** marqué `isError`, que le
modèle lit et dont il peut tenir compte. Une resource en échec renvoie une **erreur JSON-RPC** : il n'y a pas de
résultat du tout.

| Cas | Tool (`tools/call`) | Resource (`resources/read`) |
|---|---|---|
| refus métier (id inconnu) | `result` + `isError: true` | `error`, code `-32602`, avec le message |
| URI qu'aucune resource ne reconnaît | — | `error`, code `-32602` (paramètre invalide) |

La spec `2026-07-28` donne le code `-32602` (paramètres invalides) à une resource introuvable. `laravel/mcp`
renvoie `-32603` (erreur interne) pour toute erreur levée par une resource, **sauf** une `ValidationException`,
qu'il traduit en `-32602` ([ADR-0017](./decisions/0017-refus-de-resource-en-parametres-invalides.md)). La
différence se voit en HTTP : `-32603` y devient un `500`, `-32602` un `400`.

**Le même piège que pour les tools** : une exception levée dans une resource n'est transmise qu'avec
`APP_DEBUG`, et remplacée en production par « An internal server error occurred. ». Les resources étendent donc
`LibraryResource`, qui traduit toute `DomainException` en `ValidationException` : son message survit en
production, et son code est le bon ([ADR-0011](./decisions/0011-resources-etageres-et-livre.md),
[ADR-0017](./decisions/0017-refus-de-resource-en-parametres-invalides.md)).

### Pas d'annotations, pas d'abonnement

Une resource peut porter des annotations (`audience`, `priority`, `lastModified`). Aucune ne nous apporte quelque
chose : l'audience est à la fois l'utilisateur et le modèle (c'est le défaut), et `lastModified` devrait changer
avec chaque livre, alors que le SDK la fixe par un attribut de classe. La spec prévoit aussi qu'un client
**s'abonne** à une resource pour être prévenu de ses changements (`resources/subscribe`) : `laravel/mcp` ne
l'implémente pas.

## Les prompts : des conversations prêtes à l'emploi

Un prompt est un **modèle de conversation** que le serveur propose et que l'**utilisateur** choisit
explicitement. Dans Claude Code, chaque prompt devient une commande slash (`/mcp__library__recommend_next_read`).
Le client appelle `prompts/list` pour les découvrir, puis `prompts/get` avec les arguments de l'utilisateur. Le
serveur renvoie des **messages** (un rôle `user` ou `assistant`, et un contenu) que le host insère dans la
conversation.

| | `recommend_next_read` | `reading_review` |
|---|---|---|
| Ce qu'il demande | la prochaine lecture, choisie dans l'étagère « à lire » | le bilan d'une année de lecture |
| Argument | `mood`, optionnel : l'envie du moment (« court », « de la SF »…) | `year`, optionnel : l'année, par défaut l'année en cours |
| Données embarquées | l'étagère « à lire », les livres lus et abandonnés avec leur note | les livres finis ou abandonnés cette année-là |

**Un argument de prompt est une chaîne, rien de plus.** Contrairement à un tool, un prompt n'a pas de JSON Schema :
chaque argument n'a qu'un nom, une description et un indicateur `required`. Ni type, ni `enum`, ni bornes.
`reading_review` reçoit donc l'année comme du texte, et c'est le serveur qui vérifie qu'il s'agit de quatre
chiffres. Un argument invalide est une **erreur de protocole** (`-32602`), comme pour une resource : il n'y a
pas de résultat `isError` pour les prompts non plus.

**Les données sont embarquées dans le message.** Le serveur lit lui-même la bibliothèque et place le JSON dans le
texte du prompt. On aurait pu insérer des liens vers les resources (`resource_link`), mais le host ou le modèle
auraient alors dû aller les lire, et chaque host ne le fait pas forcément. On aurait aussi pu ne donner qu'une
consigne (« utilise `search_books` ») : le prompt n'apporterait alors presque rien de plus qu'une phrase tapée par
l'utilisateur. Un prompt vaut par ce qu'il **prépare côté serveur** : une consigne soignée, plus un contexte
choisi. Voir [ADR-0012](./decisions/0012-prompts-donnees-embarquees.md).

**« Aujourd'hui » vient encore du serveur.** Sans `year`, `reading_review` prend l'année en cours d'après l'horloge
du lecteur (`Clock`, [ADR-0010](./decisions/0010-instants-en-utc-jours-du-lecteur.md)). Le 1ᵉʳ janvier à 0 h 30
à Paris, c'est déjà la nouvelle année.

**Le texte du prompt est en anglais**, comme toute l'interface lue par le modèle
([ADR-0001](./decisions/0001-code-en-anglais-prose-en-francais.md)). Il demande au modèle de répondre dans la
langue de la conversation.

## L'`outputSchema` : annoncer la forme du résultat

L'`inputSchema` dit au modèle **quoi envoyer**. L'`outputSchema` dit au client **quoi attendre** : c'est le JSON
Schema du `structuredContent` que le tool renvoie en cas de succès. Nos tools renvoyaient déjà du contenu
structuré, mais sans en annoncer la forme. Le client devait deviner, par exemple, que `rating` peut être `null`.

| Tools | `outputSchema` |
|---|---|
| les 9 tools qui agissent sur un livre | un **livre** : 11 champs, tous présents, `null` quand l'information manque |
| `search_books` | une **page** : `total`, `page`, `per_page`, et `books`, une liste de livres |

**Ce que la spec impose, et à qui.** Si un tool déclare un `outputSchema`, le serveur **doit** renvoyer un
`structuredContent` conforme, et le client **devrait** le valider. En cas d'erreur (`isError: true`), le schéma ne
s'applique pas : un refus n'est qu'un texte. `laravel/mcp` annonce le schéma mais ne vérifie rien. Rien
n'empêcherait donc un tool d'annoncer une forme et d'en renvoyer une autre. C'est pourquoi le **cas nominal de
chaque tool valide sa réponse réelle** contre le schéma qu'il annonce
([ADR-0013](./decisions/0013-output-schema-verifie-par-les-tests.md)).

**Le schéma d'un livre est écrit à côté de sa présentation** : `BookPresenter::schema()` à côté de
`BookPresenter::present()`. Qui modifie l'un voit l'autre, et le test de conformité signale tout écart.

## Synthèse : les erreurs, de la plus proche du métier à la plus proche du protocole

| Situation | Primitive | Ce que reçoit le client | Qui peut en tenir compte |
|---|---|---|---|
| refus métier (« "Dune" is lent to Marie… ») | tool | `result` avec `isError: true` et le message | le **modèle**, qui peut corriger et réessayer |
| argument mal formé (`"per_page": "20"`) | tool | `result` avec `isError: true` et le message de validation | le **modèle** |
| refus métier (id inconnu) | resource | `error` JSON-RPC `-32602`, avec le message (un `400` en HTTP) | le **host** |
| argument mal formé (`year: "25"`) | prompt | `error` JSON-RPC `-32602`, avec le message | le **host**, donc l'utilisateur |
| URI ou nom inconnu | resource, prompt, tool | `error` JSON-RPC `-32602` | le **host** |
| panne technique (base indisponible) | toutes | `-32603` « An internal server error occurred. » en production | personne : c'est journalisé |

**La règle** : une erreur de **tool** est un **résultat**, parce que c'est le modèle qui a choisi l'appel et qu'il
peut apprendre de son erreur. Une erreur de **resource** ou de **prompt** est une **erreur de protocole**, parce
que c'est le host qui a fait la demande. Dans les deux cas, un refus métier garde son message en production
(`LibraryTool`, `LibraryResource`). Une panne, elle, ne laisse jamais rien fuiter.
