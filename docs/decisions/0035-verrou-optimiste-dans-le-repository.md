# ADR-0035 — Un verrou optimiste, tenu par le repository

- **Statut** : Accepté (2026-10-11)

## Contexte

Chaque use case d'écriture lit un livre, le fait changer, puis le sauve. Rien ne reliait la sauvegarde à la lecture :
deux requêtes simultanées pouvaient lire le même état, et la seconde écraser la première. Deux prêts concurrents du
même livre pouvaient ainsi réussir tous les deux, alors que le domaine interdit de prêter un livre déjà prêté.

Depuis que le serveur MCP, l'API et le front écrivent dans la même bibliothèque, ces écritures simultanées ne sont
plus une hypothèse.

## Décision

- **Un verrou optimiste** : la table `books` porte une colonne `version`. Une sauvegarde est un `UPDATE … WHERE
  version = <celle qu'on a lue>`, qui incrémente la version. S'il ne touche aucune ligne, le livre a changé.
- **Le repository tient le verrou, pas l'agrégat.** Il retient la version de chaque livre qu'il a lu ou écrit, et
  l'exige à l'écriture. `Book` ne porte aucun numéro de version : le domaine ne change pas.
- **Un repository par requête** (`scoped`) : c'est lui qui se souvient de ce qui a été lu.
- **Un conflit est un refus du domaine**, `BookChangedMeanwhile`, comme `DuplicateIsbn` : une règle que l'agrégat
  seul ne peut pas tenir, tenue au bord et traduite par le repository. Le tool MCP le rend en `isError`, l'API en
  `409`, le front le traduit.
- **Pas de nouvelle tentative automatique.** L'appelant rejoue, et le domaine répond alors sur l'état réel.
- **Retirer suit la même règle** : un livre changé depuis sa lecture n'est pas retiré. Un livre déjà retiré par un
  autre n'est pas une erreur.
- **La doublure en mémoire imite le verrou**, et le trait de contrat le vérifie sur elle comme sur Eloquent, avec
  deux sessions sur les mêmes livres.

## Conséquences

- (+) Deux écritures simultanées ne peuvent plus produire un état que le domaine interdit. Vérifié pour de vrai :
  douze prêts lancés en même temps sur un livre, un seul réussit.
- (+) Aucun verrou tenu en base, aucune transaction autour des handlers.
- (+) Le domaine et les handlers n'ont pas bougé.
- (−) Le repository a un état : la version des livres lus. Un livre sauvé par un repository qui ne l'a jamais lu est
  traité comme un nouveau livre, et refusé s'il existe déjà.
- (−) L'appelant doit savoir rejouer. Un modèle le fait ; le front demande de recharger la page.
- (−) Un refus de plus, que chaque client doit connaître.

## Alternatives écartées

- **Un verrou pessimiste** (`SELECT … FOR UPDATE`) : il exige une transaction autour de chaque handler, tient un
  verrou pour des conflits rarissimes, et ne s'imite pas dans la doublure en mémoire.
- **La version dans l'agrégat** : la forme la plus répandue, mais `Book` porterait un nombre sans sens métier, que le
  repository devrait modifier après chaque sauvegarde.
- **Rejouer automatiquement dans le handler** : chaque handler porterait une boucle, pour un cas où répondre « relis
  le livre » suffit.
- **Une condition métier dans le `UPDATE`** (`WHERE loan_borrower IS NULL`) : la règle du domaine serait recopiée en
  SQL, une fois par use case.
