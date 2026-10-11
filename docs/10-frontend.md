# 10 — Le frontend : un client OAuth public, et un hexagone sans domaine

> Étape 10 de la [roadmap](./roadmap.md). Un navigateur est un **client OAuth public**, exactement comme Claude Code :
> il ne peut garder aucun secret, il prouve qu'il a demandé le code par PKCE, et il nomme le serveur qu'il vise. Le
> construire, c'est voir de l'autre côté ce que fait un client MCP. C'est aussi l'occasion de montrer qu'une
> architecture hexagonale a du sens côté client, à condition de ne pas y recopier le domaine.

## Ce qu'on veut apprendre

- **Le parcours OAuth vu du client** : découvrir le serveur d'autorisation depuis le `401` de l'API, demander le code
  avec PKCE et `resource`, garder le token, le rafraîchir. C'est la case que l'étape 9 laissait ouverte : un vrai
  client suit la découverte jusqu'à `/api`.
- **Ce qu'est le cœur d'un client** quand les règles vivent sur le serveur : des use cases et un modèle de lecture,
  pas des invariants ([ADR-0029](./decisions/0029-hexagone-front-sans-domaine-duplique.md)).
- **Un client branché sur le contrat** : les types du client HTTP sont générés depuis l'OpenAPI de l'étape 9. Une
  dérive de l'API casse la compilation du front
  ([ADR-0032](./decisions/0032-client-http-type-par-le-contrat.md)).

## Le cœur d'un client, quand le domaine est ailleurs

Le domaine de Marque-page vit dans le back : c'est lui qui sait qu'on ne prête pas un livre de la liste d'envies. Le
réécrire en TypeScript donnerait **deux sources de vérité**, qui divergeraient au premier changement. Le front n'a donc
pas de domaine au sens du back. Il a pourtant un cœur, et l'hexagone y rend les mêmes services :

```
src/
├── library/
│   ├── core/            le cœur : ne connaît ni React, ni fetch, ni le navigateur
│   │   ├── model/       le modèle de lecture : Book, ReadingStatus, Refusal…
│   │   ├── ports/       Library (parler à l'API), Session (le token)
│   │   └── use-cases/   ce que fait le client : chercher, prêter, ajouter…
│   ├── adapters/http/   l'adaptateur secondaire : l'API REST, typée par le contrat OpenAPI
│   └── ui/              React : l'adaptateur primaire, comme le serveur MCP côté back
├── auth/                le client OAuth (même découpage)
└── app/                 la racine de composition : le seul endroit qui branche un adaptateur sur un port
tests/                   hors de src, comme côté back
└── support/             le double en mémoire et les fabriques de livres
```

Les tests vivent hors de `src/`, comme côté back où `InMemoryBookRepository` est dans `tests/Support`. Rangé dans
`core/`, le test d'un use case importerait le double, donc un adaptateur : la règle des couches l'interdirait, et elle
aurait raison.

| Côté back | Côté front |
|---|---|
| le domaine : `Book`, ses invariants | **rien** : les invariants restent au serveur |
| les use cases (`LendBookHandler`) | les use cases du client (`lendBook`) : appeler le port, rendre le livre ou le refus |
| les ports (`BookRepository`) | les ports (`Library`, `Session`) |
| les adaptateurs secondaires (Eloquent, double en mémoire) | les adaptateurs secondaires (HTTP, double en mémoire) |
| les adaptateurs primaires (tools MCP, contrôleurs REST) | l'adaptateur primaire (React) |
| PHPArkitect | la règle `no-restricted-imports` d'ESLint, couche par couche |

Le cœur du front est **mince**, et c'est la leçon : quand le serveur porte les règles, un client bien fait ne garde que
l'orchestration et la traduction. Son hexagone sert à autre chose qu'au métier : tester les use cases sans navigateur
ni réseau, changer d'API sans toucher aux écrans, et empêcher React de fuir dans la logique.

**Le double en mémoire ne contient aucune règle.** Il garde des livres, et on lui dit à l'avance quel refus opposer
(« la prochaine action sera refusée par `book-already-lent` »). Il ne sait pas pourquoi un prêt serait refusé :
le lui apprendre, ce serait recopier le domaine.

## Les boutons, quand les règles sont au serveur

Sans règle côté client, l'écran ne sait pas qu'un livre déjà lu ne peut pas être commencé. Deux réponses :

1. **Tenter, et afficher le refus.** L'écran propose toutes les actions. L'API refuse ce qui n'est pas permis, avec un
   *Problem Details* dont le `detail` est le message du domaine (« Cannot start reading "Dune": its reading status is
   read »). L'écran l'affiche tel quel.
2. **L'API annonce les actions possibles** (des *affordances*, à la manière de l'hypermédia) : chaque livre arrive
   avec la liste de ce qu'on peut en faire, calculée par le domaine.

On commence par la première ([ADR-0031](./decisions/0031-tenter-puis-afficher-le-refus.md)) : elle ne demande rien
au back, et le message du domaine, écrit une seule fois, sert enfin un humain. Son coût est ergonomique (un bouton
« Rendre » sur un livre qui n'est pas prêté). La seconde est la bonne évolution le jour où ce coût gêne : elle garde
une seule source de vérité.

## Le token dans le navigateur

Le brouillon de l'IETF *OAuth 2.0 for Browser-Based Apps* classe les architectures de la plus sûre à la moins sûre.
En tête : le **BFF** (*Backend For Frontend*), un serveur qui fait l'OAuth et ne donne au navigateur qu'un cookie de
session. Le navigateur ne voit jamais de token, et un script injecté ne peut pas en voler.

On fait l'inverse, en connaissance de cause ([ADR-0030](./decisions/0030-spa-cliente-oauth-publique.md)) : une
**SPA cliente publique**, parce que l'objet de l'étape est de **voir** le parcours OAuth depuis un client. Avec un
BFF, le navigateur ne ferait plus d'OAuth. Le risque se réduit ainsi :

- **le token reste en mémoire**, jamais dans `localStorage` ni `sessionStorage`, que tout script de la page peut lire ;
- seuls le `code_verifier` et le `state` de PKCE passent par `sessionStorage`, le temps de la redirection : ils ne
  valent rien sans le code, et le code ne vaut rien sans eux ;
- un **rechargement** perd le token : le front relance l'autorisation, que Passport accorde sans écran puisque le
  consentement est déjà donné. Le coût est une redirection ;
- le client est **pré-enregistré** (public, sans secret, avec une seule `redirect_uri`) : pas d'enregistrement
  dynamique depuis un navigateur, qui créerait un client par visite ;
- **CORS** n'ouvre `/api`, `/oauth/token` et les métadonnées qu'à l'origine du front.

## La stack

| Besoin | Choix | Pourquoi |
|---|---|---|
| Langage | **TypeScript 5.9**, en mode strict | le strict joue le rôle de PHPStan au niveau max. TypeScript 7 (réécrit en Go) existe, mais typescript-eslint et openapi-typescript exigent encore une version antérieure |
| Base | **Vite + React 19** | le standard ; Vite sert aussi le serveur de dev |
| Client HTTP | **openapi-typescript + openapi-fetch** | des types générés depuis `docs/api/openapi.json`, et un client mince qui s'en sert |
| État serveur | **TanStack Query** | cache, invalidation, états de chargement : dans l'adaptateur React, jamais dans le cœur |
| Routage | **React Router** | |
| OAuth | **oauth4webapi** | une bibliothèque minimaliste et certifiée : PKCE et la découverte ne s'écrivent pas à la main |
| Tests | **Vitest 4** et **Testing Library** ; **Playwright** pour le bout en bout | les use cases se testent avec le double en mémoire, comme côté back. Vitest 5 existe, mais le runner de Stryker n'en tue aucun mutant (voir plus bas) |
| Qualité | **ESLint** (typescript-eslint strict), **Prettier**, la règle **`no-restricted-imports`** par couche, **Stryker** sur le cœur | la même exigence que le back : MSI à 100 % sur le cœur |
| Outillage | un service **`node`** (Node 24 LTS) dans le compose, les cibles `make front-*` | tout tourne dans un conteneur, et **le front entre dans `make quality`** : la porte reste unique |

## Ce que le socle a appris

- **Un plugin de qualité peut apporter des failles.** `eslint-plugin-boundaries`, prévu pour vérifier les couches,
  tirait `handlebars` (une vulnérabilité critique) et `braces` (haute, sans version corrigée). Forcer une version
  n'aurait rien réglé. La règle native d'ESLint, `no-restricted-imports`, exprime les mêmes interdits couche par
  couche, sans aucune dépendance. Une sonde (React et un adaptateur importés depuis le cœur, un adaptateur depuis
  l'UI) le vérifie : les trois fuites sont refusées.
- **Un outil de qualité peut échouer en silence.** Avec Vitest 5, Stryker déclarait survivants des mutants qu'aucun
  test n'avait exécutés (`0 tests per mutant`) : un `{ ...criteria }` remplacé par `{}` passait. Avec Vitest 4, le même
  mutant meurt. Vitest reste en 4.1 tant que le runner de Stryker ne suit pas, comme TypeScript reste en 5.9 pour
  typescript-eslint. Sans le contrôle « ce mutant-là doit mourir », on aurait cru à un trou dans les tests.
- **Le mutation testing trouve aussi de la logique morte.** Le premier `searchBooks` retirait les espaces du texte
  cherché, et le mutant qui supprimait ce `trim()` survivait. Aucun test ne manquait : ce code ne servait à rien,
  puisque l'API ignore déjà les espaces (`TrimStrings`) et traite un texte vide comme l'absence de critère. Il est
  parti, et `searchBooks` ne fait plus que déléguer au port. Quand le cœur n'a rien à muter, Stryker répond `n/a` sans
  casser la porte.

## La connexion, pour de vrai

Dans un navigateur, sur `http://localhost:5173`, le journal nginx (`make mcp-log`) montre le parcours du front :

```
GET  /.well-known/oauth-protected-resource/api   (suivi depuis le WWW-Authenticate du 401)
GET  /.well-known/oauth-authorization-server
GET  /oauth/authorize                            (l'écran d'accord : « Pour le serveur http://localhost:8090/api »)
POST /oauth/authorize                            (Autoriser)
GET  /.well-known/oauth-protected-resource/api   (de retour sur /callback, la page a tout oublié : nouvelle découverte)
GET  /.well-known/oauth-authorization-server
POST /oauth/token                                (le code, son verifier et le resource)
```

En base, le token de « Marque-page web » porte `resource = http://localhost:8090/api`. C'est la case que l'étape 9 avait
laissée ouverte : un vrai client OAuth a suivi la découverte jusqu'à l'API, et son token n'ouvre qu'elle.

Trois points vus en l'écrivant :

- **La découverte se refait au retour** : la redirection vers le serveur d'autorisation est une navigation, et la
  mémoire de la page ne survit pas. Seuls le `code_verifier` et le `state` ont traversé, par `sessionStorage`.
- **React lance deux fois les effets en développement** (`StrictMode`) : sans garde, le retour échangerait deux fois
  le même code, et la seconde fois échouerait. Un code ne vaut qu'un échange, le composant n'en tente qu'un.
- **Une session qui s'achève n'est pas une panne.** Si le token expire et que son rafraîchissement échoue,
  l'adaptateur HTTP lève `NotSignedIn`, et l'application relance l'autorisation au lieu d'afficher une erreur. C'est
  Stryker qui l'a fait voir : le message de `NotSignedIn` survivait à toutes ses mutations, parce que rien ne devait le
  lire.

Et deux défauts du contrat, que le front a révélés et qu'on a corrigés côté API : `reading_status` y était un `string`
quelconque (il est désormais l'enum `ReadingStatus`), et les `operationId` reprenaient le namespace des contrôleurs
(`infrastructure.searchBooks`). Les routes portent maintenant le nom de leur use case.

## La bibliothèque

Une liste en colonnes (titre et auteur, statut, lecture, note, prêt), triée par titre, avec des filtres, une
recherche et la pagination de l'API. L'identité visuelle tient à peu de chose : une Caslon pour les titres, une
chasse fixe pour les dates, et **le signet**, un repère cobalt dans la marge des livres en cours. Une première maquette
montrait les livres comme des dos debout sur des étagères : jolie, mais on n'y lisait rien d'un coup d'œil.

Ce qui revient au cœur, et que Stryker juge :

- **Les étagères du front sont des recherches**, comme le glossaire le dit de celles du back
  ([02](./02-langage-ubiquitaire.md)) : « En cours » est `reading_status=reading`, « Liste d'envies » est
  `owned=false`. Ce ne sont pas des règles métier, seulement des critères nommés.
- **Les compteurs** : une recherche par étagère, avec `per_page=1`. Seul le `total` compte, et l'API le donne
  quelle que soit la taille de la page.
- **Le nombre de pages**, qui vient du `total` et de la taille de page de l'API. L'API pagine par 20 :
  sans pagination, la bibliothèque de démo (23 livres) en aurait caché trois sans le dire.

Deux enseignements :

- **Stryker a encore trouvé de la logique morte.** Le cœur n'envoyait pas de texte vide (`text === '' ? {} : …`), et
  le mutant qui supprimait cette condition survivait : un texte vide ne filtre rien, ni dans l'API ni dans le double.
  La condition n'avait de sens que pour une URL propre, sans `?query=` vide. Elle est passée dans l'adaptateur HTTP,
  qui traduit vers la query string.
- **Les polices sont embarquées** (`@fontsource`), pas chargées depuis Google Fonts. Ce CDN reçoit l'adresse IP de
  chaque visiteur, et un tribunal allemand l'a jugé contraire au RGPD en 2022. Embarquer les fontes évite aussi une
  dépendance réseau au premier affichage.

## La fiche d'un livre et ses actions

Choisir un livre dans la liste ouvre sa fiche, à droite (ou dessous, sur un téléphone) : ses dates tamponnées, sa
note, son prêt, et **toutes** ses actions (commencer, terminer, abandonner, noter, prêter, rendre, acquérir,
retirer). L'API décide ([ADR-0031](./decisions/0031-tenter-puis-afficher-le-refus.md)), et le front affiche son refus
**en français**, traduit par son `type` ([ADR-0033](./decisions/0033-refus-traduits-par-leur-type.md)).

- **Un use case pour toutes les actions** (`actOn`) : l'écran décrit l'action (`{ kind: 'lend', borrower: 'Marie' }`),
  le cœur choisit la méthode du port. Son issue est une **valeur**, le livre mis à jour ou un refus : un refus est une
  réponse prévue, pas une panne. Les vraies pannes (un `500`, un refus sans *Problem Details*) restent des exceptions.
- **Le double applique l'effet sans les conditions** : commencer met le livre en cours, sans vérifier qu'il pouvait
  l'être. Un test qui veut un refus le programme (`refuseNext('book-lent')`).
- **Une action réussie rafraîchit tout** : la fiche prend le livre rendu par l'API, la liste et les compteurs sont
  relus.
- **Le retrait demande confirmation**, puisque `remove_book` est le seul use case destructif sans retour.

Ce que les tests ont fait voir :

- **L'élision du français.** « Impossible de abandonner » : devant une voyelle, *de* devient *d'*. La table porte
  la préposition avec le verbe.
- **Une entrée qui ne servait à rien.** Stryker laissait survivre la mutation de « Impossible de noter » : un refus
  de transition de lecture ne naît que de commencer, terminer ou abandonner. La table ne garde que ces trois verbes,
  et retombe sur le message du domaine pour le reste.
- **Un délai trop court cache un échec.** Un mutant faisait échouer un test d'interface, mais lentement (une recherche
  à l'écran attend avant d'abandonner), et Stryker le comptait en *timeout*, donc tué, sans l'avoir vu échouer. Stryker
  n'a pas d'équivalent au `timeoutsAsEscaped` d'Infection : son délai passe à 15 s, et deux passages donnent le même
  résultat sans un seul *timeout*.
- **Le `type` d'un refus contient l'hôte** (`http://localhost:8090/problems/book-lent`) : il change d'un environnement
  à l'autre, alors qu'un type de problème devrait être un identifiant stable. Le front n'en lit que le dernier segment.
  C'est à corriger côté API.

## Ce qu'on vérifiera

- **Les couches** : le cœur ne dépend ni de React ni des adaptateurs ; seule la racine de composition branche un
  adaptateur sur un port. ESLint le vérifie (`no-restricted-imports`), dans la porte.
- **Les use cases**, avec le double en mémoire, et le cœur entièrement couvert par Stryker.
- **L'adaptateur HTTP** contre l'API réelle : il traduit les *Problem Details* en refus du cœur.
- **Le contrat** : changer la forme d'une réponse dans l'API casse la compilation du front.
- **Pour de vrai**, dans un navigateur : la découverte depuis le `401`, l'écran d'accord qui nomme `/api`, un refus
  du domaine affiché tel quel.
