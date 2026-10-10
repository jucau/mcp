# ADR-0023 — Ne compléter que ce qu'un humain sait choisir, à partir des données du lecteur

- **Statut** : Accepté (2026-10-10).

## Contexte

`completion/complete` propose des valeurs pour les arguments des prompts et des templates de resource
([08](../08-completions.md)). La primitive sert l'**utilisateur** qui remplit un formulaire, jamais le modèle. Ses
valeurs sont des **chaînes nues**, sans libellé.

Marque-page a trois candidats : `reading_review.year`, `recommend_next_read.mood` et l'`id` du template
`library://books/{id}`.

## Décision

- On ne complète un argument que si **un humain sait choisir parmi ses valeurs brutes**. Seul
  `reading_review.year` passe ce filtre. `mood` est du texte libre, et l'`id` est un UUID.
- Les années proposées sont **celles où le lecteur a terminé ou abandonné un livre**, la plus récente d'abord. Elles
  viennent d'une nouvelle lecture du port `BookQueries`, `yearsWithFinishedBooks(ReaderId)`, scopée au lecteur
  comme toutes les autres.
- Le `LibraryServer` annonce la capability `completions`.

## Conséquences

- (+) Chaque suggestion mène à une revue qui a du contenu, et rien n'est proposé qui ne serve pas.
- (+) Les suggestions ne révèlent rien d'un autre lecteur : elles passent par le même scope que toute lecture.
- (−) Le port de lecture grossit d'une méthode, pour un seul consommateur.
- (−) L'année courante n'est pas proposée tant que le lecteur n'y a rien terminé, alors que c'est la valeur par
  défaut du prompt. C'est voulu : la proposer mènerait à une revue vide.

## Alternatives écartées

- **Proposer les N dernières années, sans lire la base** : plus simple, mais propose des années vides et
  n'enseigne rien du contrôle d'accès que la spec exige.
- **Compléter l'`id` par titre** (l'utilisateur tape « dune », on renvoie l'UUID) : la liste afficherait des UUID,
  illisibles faute de libellé dans le protocole.
- **Charger tous les livres terminés et en extraire les années dans le prompt** : ça marche, mais ça lit toute la
  bibliothèque à chaque frappe, alors qu'une requête `distinct` suffit.
