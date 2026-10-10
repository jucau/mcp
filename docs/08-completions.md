# 08 — Les complétions d'arguments

> Une petite primitive, qui ne sert **ni le modèle ni le serveur** : elle sert l'**humain** qui remplit un
> formulaire. La comprendre, c'est surtout comprendre **à qui** elle parle.

## À quoi ça sert

Un prompt et un template de resource prennent des arguments (`reading_review` prend `year`, `library://books/{id}`
prend `id`). Quand l'**utilisateur** les remplit dans son client, celui-ci peut demander au serveur de quoi lui
proposer une liste déroulante, comme l'autocomplétion d'un IDE :

```json
→ {"jsonrpc":"2.0","id":7,"method":"completion/complete","params":{
    "ref":{"type":"ref/prompt","name":"reading_review"},
    "argument":{"name":"year","value":"20"},
    "context":{"arguments":{}}}}
← {"jsonrpc":"2.0","id":7,"result":{"resultType":"complete",
    "completion":{"values":["2026","2025","2024"],"total":3,"hasMore":false}}}
```

- **`ref`** désigne ce qu'on remplit : `ref/prompt` (par son `name`) ou `ref/resource` (par l'`uri` du template).
- **`argument`** donne le nom de l'argument et **ce que l'utilisateur a tapé jusqu'ici**.
- **`context.arguments`** porte les arguments **déjà remplis** du même prompt ou template, pour qu'une suggestion
  dépende d'une autre (le framework selon le langage choisi, par exemple).
- La réponse liste au plus **100 valeurs**, rangées par pertinence, avec `hasMore` si la liste est tronquée.

## Qui contrôle quoi

C'est le prolongement direct de [04](./04-les-trois-primitives.md) : les prompts sont **contrôlés par
l'utilisateur**, et la complétion n'existe que pour l'aider à les remplir. **Le modèle ne l'appelle jamais.** Elle
ne sert pas non plus aux tools : leurs arguments, c'est le modèle qui les remplit, guidé par le JSON Schema.

Conséquence : une complétion n'a de valeur que si **un humain sait choisir** parmi les valeurs proposées. C'est
la règle qui décide où on en met ([ADR-0023](./decisions/0023-completer-seulement-ce-qu-un-humain-choisit.md)).

## Une capability qui conditionne vraiment

Le serveur doit **annoncer** `completions` dans ses capabilities. Ce n'est pas décoratif : sans elle, `laravel/mcp`
répond `-32601` (méthode introuvable) à tout `completion/complete`, même si un prompt sait compléter. Le
`LibraryServer` l'ajoute donc dans son `boot()`.

## Ce que Marque-page complète, et ce qu'il ne complète pas

| Argument | Complété ? | Pourquoi |
|----------|-----------|----------|
| `reading_review.year` | **Oui** : les années où le lecteur a terminé ou abandonné un livre, la plus récente d'abord, filtrées par ce qu'il a tapé | Un humain choisit très bien une année, et ne proposer que celles qui ont des lectures évite une revue vide |
| `recommend_next_read.mood` | Non | Texte libre : proposer des valeurs, ce serait inventer un vocabulaire que le domaine n'a pas (pas de genres) |
| `library://books/{id}` | Non | Une complétion ne renvoie que des **chaînes nues**, sans libellé : une liste d'UUID n'aide personne à choisir |

Le cas de l'`id` est instructif. On pourrait chercher par titre ce que l'utilisateur tape et renvoyer les UUID
correspondants, mais la liste déroulante afficherait `0199a3c2-…` et pas *Dune* : le protocole n'a pas de place
pour un libellé. Un argument qui n'est **pas fait pour un humain** ne gagne rien à être complété.

Pour un argument qu'on ne complète pas, le SDK répond une liste **vide**, pas une erreur : c'est ce que la spec
attend d'un serveur qui n'a rien à proposer.

## Une complétion est une lecture, avec les mêmes règles

La spec exige de **contrôler l'accès aux suggestions** et d'empêcher les **fuites d'information par la complétion**.
Ici, c'est concret : les années proposées disent quand un lecteur a lu. Elles viennent donc d'une lecture du port
`BookQueries` **scopée au lecteur courant**, comme toutes les autres ([ADR-0018](./decisions/0018-un-lecteur-par-livre-sans-compte-dans-le-domaine.md)),
et en HTTP la requête passe par le même OAuth que le reste ([07](./07-authentification.md)).

La spec recommande aussi de limiter le débit : un client appelle la complétion **à chaque frappe**. On ne le fait
pas, car le seul serveur exposé n'écoute que sur `127.0.0.1` ; c'est à reprendre avant toute exposition réelle.

## Ce que fait le SDK, et ses écarts

- Un prompt ou une resource devient complétable en implémentant **`Completable`** :
  `complete(string $argument, string $value, array $context): CompletionResponse`.
- `CompletionResponse::match([...])` filtre une liste par **préfixe**, sans tenir compte de la casse ;
  `CompletionResponse::result([...])` renvoie la liste telle quelle et tronque à 100.
- **`total` ment** quand la liste est tronquée : le SDK y met le nombre de valeurs **renvoyées**, alors que la spec
  en fait le nombre **total** de correspondances. Sans conséquence ici (quelques années, jamais 100).
- **Une exception pendant la complétion devient une liste vide**, silencieusement. C'est un choix défendable (une
  autocomplétion ne doit pas casser la saisie), mais un bug y passera inaperçu : les tests le couvrent.
