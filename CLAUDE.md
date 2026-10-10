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

## Architecture

**Hexagonale ciblée** ([ADR-0006](docs/decisions/0006-architecture-hexagonale-ciblee.md)) : un context,
`app/Library/{Domain,Application,Infrastructure}`, namespace `App\Library\…`. Le **serveur MCP est un
adaptateur primaire** : il vit en `Infrastructure`, traduit et appelle un handler, sans jamais porter de règle
métier. Les règles de couches (domaine pur, `Application` sans persistance directe ni façade, **aucune couche
intérieure ne connaît `Laravel\Mcp`**) sont vérifiées par PHPArkitect. **Un tool = un use case**, il étend
`LibraryTool`, valide la forme de ses arguments en strict et présente via un presenter partagé
([ADR-0008](docs/decisions/0008-tools-adaptateurs-minces-refus-metier.md)).

## Tests

École **classiciste**, **par les use cases** :

- **On teste un use case, pas une classe** : le handler, avec le double in-memory. Le domaine (agrégat, value
  objects, invariants) est couvert **à travers** les use cases : pas de tests unitaires du domaine.
- **Arrange par le domaine, act par le seul use case testé** : l'état initial se construit avec `Book` et ses
  comportements (`Tests\Support\Library\Books::aBook()`), jamais en appelant un autre use case.
- **Doubles d'état, jamais de mocks d'interaction** : `Mockery` et `createMock` sont interdits.
- **Asserter l'effet observable**, relu depuis le repository, jamais un mécanisme.
- **Les adaptateurs** (Eloquent) ont leurs tests Feature ; un même **trait de contrat** est joué par le double
  in-memory (Unit) **et** l'adaptateur réel (Feature).

## Migrations

**Une migration ne touche qu'une table** : chacune s'applique, échoue et se rejoue seule.

## Données de démo

**Chaque feature livre ses données de démo** dans `LibrarySeeder`, avec des cas variés. L'agrégat se seede
**via les use cases**, jamais par insertion directe : les données de démo respectent les mêmes invariants
que les vraies. Lancer : `make seed`.

## Outils IA

- **Laravel Boost** fournit un serveur MCP (schéma de BDD, tinker, recherche dans la doc versionnée…), les
  guidelines [`AGENTS.md`](AGENTS.md) et les skills de `.claude/skills/`. Sa config est `boost.json`.
- `.mcp.json` lance Boost **dans le conteneur** (`docker compose exec -T php php artisan boost:mcp`) :
  `boost:install` le réécrit en `php artisan boost:mcp` local, qui échoue sans PHP sur l'hôte — à restaurer
  après chaque install.

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

**`make ci`** simule une CI : base de test neuve, la porte entière, puis les données de démo seedées sur la base
de test (jamais celle de dev) avant de la revider.

Ordre : **ide-helper** → **rector** → **pint** (écrivent ; pint en dernier, il importe et raccourcit les
noms qualifiés qu'ide-helper et rector écrivent) → **stan** → **ark** → **test** → **infection**.
Configs : `config/ide-helper.php`, `rector.php`, `pint.json`, `phpstan.neon` (Larastan, niveau **max**),
`phparkitect.php`, `infection.json5`.

**Infection** (mutation testing) juge `Domain` + `Application` contre la suite **Unit seule**, `minMsi` et
`minCoveredMsi` à **100** : un mutant survivant est un comportement qu'aucun test de use case ne contraint, et
fait échouer la porte. PHPStan sert de second tueur. Un mutant **équivalent** (intuable par construction) se
traite en réécrivant le code, pas en l'ignorant. **IDE Helper** lit le schéma de la
**BDD de test** (`--env=testing`) : `make test-db` après chaque nouvelle migration.

**Pao** (`laravel/pao`) : quand un **agent** lance la porte, Rector, Pint, PHPStan et PHPUnit répondent **une
ligne JSON** au lieu de centaines de lignes ANSI ; les runs humains gardent la sortie humaine. Pao se
déclenche sur `AI_AGENT`, que `docker compose exec` ne transmet pas : le Makefile le relaie (`AGENT_ENV`)
*uniquement s'il est posé chez l'appelant*. Il est **désactivé sous Infection** (`PAO_DISABLE=true`) : Infection
lit la sortie humaine de PHPUnit.

## Git

- Ne **commiter / pusher que sur demande explicite** de l'utilisateur.
- **Commits fonctionnels uniquement** : jamais de commit « fix » qui rattrape le précédent. On **amende**
  (ou on *squash*) **tant que le commit n'est pas poussé**. Une fois poussé, par défaut, la correction devient un
  nouveau commit fonctionnel (son gitmoji dit sa nature : 🐛, ♻️…), pas un « fix » du précédent. `main` poussé ne
  se réécrit que **sur demande explicite de l'utilisateur** : *fixup* sur les commits d'origine, **porte verte sur chaque
  commit réécrit**, branche de sauvegarde locale, puis `--force-with-lease`, jamais `--force`.
- **Commits : gitmoji, anglais, titre seul** — l'emoji de la nature du changement suivi directement du
  message, **aucun corps**, **aucun co-auteur**. Ex. : `📝 document the MCP primitives`, `✨ add the
  search_books tool`, `🔧 add PHPStan`.
