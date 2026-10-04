# ADR-0019 — Des comptes en infrastructure, et un lecteur courant fermé par défaut

- **Statut** : Accepté (2026-10-04)

## Contexte

Le domaine sert un lecteur, désigné par un `ReaderId`
([ADR-0018](./0018-un-lecteur-par-livre-sans-compte-dans-le-domaine.md)). En HTTP, ce lecteur doit venir du token
OAuth ; en stdio, la spec MCP demande de le lire dans l'environnement. Aujourd'hui, `CurrentReader` lit toujours la
configuration (`LIBRARY_READER`), quel que soit le transport.

Passport authentifie des comptes Eloquent. Pour un token *client credentials*, il met l'identifiant du **client** (un
UUID) dans le `sub`, et compte sur la différence de type avec les identifiants d'utilisateur (des entiers) pour ne
jamais confondre les deux.

## Décision

- **Un compte `User` en infrastructure** : e-mail, mot de passe, fuseau horaire, et une colonne **`reader_id`**
  (UUID, unique) qui désigne le lecteur incarné. Sa **clé primaire reste un entier**, celle que Passport attend.
- **`CurrentReader` devient un port d'infrastructure à deux implémentations** :
  - le lecteur **configuré** (`LIBRARY_READER`), branché par défaut : c'est celui de stdio ;
  - le lecteur **authentifié**, que la route `Mcp::web` installe explicitement, et qui lit le compte du guard `api`.
- **Fermé par défaut** : le lecteur authentifié **échoue** s'il n'y a pas de compte. Il ne retombe jamais sur le
  lecteur configuré.
- **Le fuseau horaire suit le lecteur courant** : celui du compte en HTTP, `LIBRARY_READER_TIMEZONE` en stdio.
- **Pas d'inscription** : les comptes de démo viennent du seeder. Un écran de connexion minimal, pour Passport.

## Conséquences

- (+) Le domaine et l'application ne changent pas : ils reçoivent toujours un `ReaderId`.
- (+) Une route HTTP qui perdrait son middleware d'authentification échouerait, au lieu de servir la bibliothèque
  du lecteur configuré à n'importe qui. Un test le vérifie.
- (+) Le compte et le lecteur restent deux notions : changer d'e-mail ou de mot de passe ne touche pas aux livres.
- (−) Deux identifiants par personne (celui du compte, celui du lecteur), reliés par une colonne.
- (−) Une configuration de plus par transport, à relire quand on ajoutera un transport.

## Alternatives écartées

- **La clé primaire du compte = le `ReaderId`** : un seul identifiant, mais en UUID, il partagerait le type des
  identifiants de client de Passport, et on perdrait la garantie qui distingue un token d'utilisateur d'un token de
  client.
- **Choisir le lecteur selon qu'un compte est connecté** (le compte s'il y en a un, sinon la configuration) : c'est
  ouvert par défaut. Le jour où la route HTTP perd son `auth:api`, tout visiteur devient le lecteur configuré.
- **Choisir selon `runningInConsole()`** : les tests HTTP tournent eux aussi en console, et le critère ne dit rien
  du transport réellement utilisé.
