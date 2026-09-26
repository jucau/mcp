# Marque-page — projet pédagogique MCP sur Laravel

Projet d'apprentissage personnel. But : comprendre le **Model Context Protocol**
en construisant un **serveur MCP** sur **Laravel** (`laravel/mcp`), sur le domaine d'une **bibliothèque
personnelle**.

## À lire en premier

👉 **`docs/README.md`** est la source de vérité. Lire [`docs/00-mcp-en-bref.md`](docs/00-mcp-en-bref.md)
et la [roadmap](docs/roadmap.md) avant toute proposition de code. Le code se conforme à la doc, jamais
l'inverse.

**Règle de maintenance de ce fichier.** Toute ligne ajoutée ici doit être **inconditionnelle** — vraie quel
que soit le travail en cours. Sinon : **vérifiable mécaniquement → la porte qualité** ; **décision, doctrine,
état ou historique → `docs/`**.

## Décisions structurantes

Une ADR par décision dans [`docs/decisions/`](docs/decisions/README.md), avec son *pourquoi*. Ne restent ici
que les règles qui valent à chaque instant :

- **Code en anglais**, prose des docs en français, termes MCP sous leur nom officiel
  ([ADR-0001](docs/decisions/0001-code-en-anglais-prose-en-francais.md)).
- **Commentaires : par défaut, AUCUN.** Le code doit se lire seul : un commentaire qui explique **ce que fait**
  le code, alors que le lire est trivial, est du bruit — il se supprime, sans discussion. Devant l'envie d'en
  écrire un, on **refactore** : extraire une méthode, renommer une variable, nommer une condition. Pas de
  docblock qui répète la signature (`/** Run the migrations. */`), pas de commentaire de section qui annonce
  le bloc suivant. Seule exception : un **pourquoi non déductible** à la lecture (contrainte externe, décision
  d'archi, piège mesuré), court, qui apprend quelque chose qu'aucun nom ne peut porter. Jamais l'historique
  d'un changement : c'est le rôle du commit et des ADR. Vaut **partout** : prod, tests, configs, Makefile.
  Les **descriptions** de tools/resources/prompts ne sont pas des commentaires : c'est l'interface que lit le
  modèle, elles se soignent.

## Méthode de travail

- On suit la [roadmap](docs/roadmap.md) **étape par étape**. Chaque concept MCP rencontré est **documenté
  dans `docs/`** au moment où on le rencontre : une explication ne reste jamais seulement dans le chat.
- Toute décision structurante → **une ADR**, au moment où elle est prise.
- L'utilisateur apprend : expliquer le **pourquoi** (du protocole, du choix de design), pas seulement le
  *comment*. Privilégier les exemples complets.

## Posture de l'assistant (important)

- **Ne jamais être complaisant.** Challenger l'utilisateur dès qu'une affirmation est inexacte, imprécise ou
  discutable — y compris ses choix de design. Exposer le contre-argument, proposer l'alternative, trancher
  avec un avis franc. Un accord doit être mérité, jamais réflexe.

## Environnement de dev (Docker)

Stack : **nginx (8090) → php-fpm 8.5 → postgres 18** ([ADR-0005](docs/decisions/0005-environnement-docker-uniquement.md)).

- **Tout tourne dans le conteneur `php`** (composer, artisan, outils qualité) ; le **Makefile** pilote
  l'ensemble : `make artisan ARGS="..."`, `make composer ARGS="..."`.
- Postgres : service `postgres`, db/user/pass = `marquepage`/`marquepage`/`secret`, exposé sur `5434`.
- `.env` et `.env.testing` (locaux, non versionnés) ; `.env.example` et `.env.testing.example` versionnés.
- Tests sur une **BDD dédiée** `marquepage_testing`, forcée par `phpunit.xml`, créée par `make test-db`.

## Qualité (`make`)

**`make quality` est la porte unique**, lancée **en entier** et **verte** avant chaque commit — jamais un
sous-ensemble. Les outils qui écrivent corrigent au passage : la porte passe **avant** le commit, sinon la
correction arrive dans un commit de rattrapage.

Ordre : **pint** (écrit) → **test**. Configs : `pint.json`.

## Git

- Ne **commiter / pusher que sur demande explicite** de l'utilisateur.
- **Commits fonctionnels uniquement** : jamais de commit « fix » qui rattrape le précédent. On **amende**
  (ou on *squash*).
- **Commits : gitmoji, anglais, titre seul** — l'emoji de la nature du changement suivi directement du
  message, **aucun corps**, **aucun co-auteur**. Ex. : `📝 document the MCP primitives`, `✨ add the
  search_books tool`, `🔧 add PHPStan`.
