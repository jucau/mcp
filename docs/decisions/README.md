# Journal de décisions (ADR)

Ce dossier consigne les **décisions structurantes** du projet sous forme d'ADR
(*Architecture Decision Records*) : de courts documents qui capturent **le contexte, la
décision, et surtout le *pourquoi***. L'objectif pédagogique est de pouvoir, plus tard,
répondre à « pourquoi avons-nous fait ça comme ça ? » sans deviner.

## Format

Chaque ADR suit la même trame :
- **Statut** : Proposé / Accepté / Remplacé par ADR-XXXX
- **Contexte** : la situation et les forces en présence
- **Décision** : ce qu'on a tranché
- **Conséquences** : ce que ça implique (positif et négatif)
- **Alternatives écartées** : et pourquoi

Un ADR est **immuable** une fois accepté : si on change d'avis, on en écrit un nouveau qui
**remplace** l'ancien (on ne réécrit pas l'histoire).

## Index

| # | Titre | Statut |
|---|-------|--------|
| [0001](./0001-code-en-anglais-prose-en-francais.md) | Code en anglais, prose en français | Accepté |
| [0002](./0002-laravel-mcp-comme-sdk-serveur.md) | `laravel/mcp` comme SDK du serveur | Accepté |
| [0003](./0003-domaine-autonome-bibliotheque-perso.md) | Un domaine autonome : la bibliothèque personnelle | Accepté |
| [0004](./0004-stdio-avant-http.md) | Transport stdio d'abord, Streamable HTTP ensuite | Accepté |
| [0005](./0005-environnement-docker-uniquement.md) | Environnement 100 % Docker, piloté par un Makefile | Accepté |
| [0006](./0006-architecture-hexagonale-ciblee.md) | Architecture hexagonale ciblée, MCP comme adaptateur primaire | Accepté |
| [0007](./0007-un-agregat-book-pret-en-value-object.md) | Un seul agrégat `Book`, le prêt en value object | Accepté |
| [0008](./0008-tools-adaptateurs-minces-refus-metier.md) | Les tools sont des adaptateurs minces qui transmettent les refus métier | Accepté, complété par 0036 |
| [0009](./0009-quatre-annotations-explicites-par-tool.md) | Chaque tool déclare explicitement ses quatre annotations | Accepté |
| [0010](./0010-instants-en-utc-jours-du-lecteur.md) | Les instants en UTC, les jours dans le fuseau du lecteur | Accepté |
| [0011](./0011-resources-etageres-et-livre.md) | Les resources : quatre étagères fixes, un livre par template, les refus transmis | Accepté, erreurs remplacées par 0017 |
| [0012](./0012-prompts-donnees-embarquees.md) | Les prompts embarquent les données de la bibliothèque | Accepté |
| [0013](./0013-output-schema-verifie-par-les-tests.md) | Chaque tool annonce son `outputSchema`, et les tests vérifient qu'il le respecte | Accepté |
| [0014](./0014-presenters-services-injectes.md) | Les presenters sont des services injectés | Accepté |
| [0015](./0015-webmozart-assert-jamais-assert-natif.md) | `Webmozart\Assert`, jamais `assert()` natif, et c'est PHPStan qui l'impose | Accepté |
| [0016](./0016-http-sans-etat-deux-eres-origin-valide.md) | Un endpoint HTTP sans état, qui sert les deux époques du protocole et valide l'`Origin`' | Accepté |
| [0017](./0017-refus-de-resource-en-parametres-invalides.md) | Un refus de resource est une erreur de paramètres (`-32602`), pas une erreur interne | Accepté |
| [0018](./0018-un-lecteur-par-livre-sans-compte-dans-le-domaine.md) | Un lecteur par livre, et aucun compte dans le domaine | Accepté |
| [0019](./0019-comptes-en-infrastructure-lecteur-ferme-par-defaut.md) | Des comptes en infrastructure, et un lecteur courant fermé par défaut | Accepté, lecteur par défaut remplacé par 0021 |
| [0020](./0020-passport-serveur-d-autorisation-destinataire-implicite.md) | Passport comme serveur d'autorisation, et un destinataire de token implicite | Accepté, destinataire remplacé par 0026 |
| [0021](./0021-chaque-transport-choisit-son-lecteur.md) | Chaque transport choisit son lecteur, et aucun lecteur n'est servi par défaut | Accepté |
| [0022](./0022-invariants-gardes-par-le-constructeur.md) | Le constructeur d'un value object garde ses invariants, le constructeur nommé normalise | Accepté |
| [0023](./0023-completer-seulement-ce-qu-un-humain-choisit.md) | Ne compléter que ce qu'un humain sait choisir, à partir des données du lecteur | Accepté |
| [0024](./0024-api-rest-une-url-par-use-case.md) | L'API REST parle métier : une URL par use case, et les refus en *Problem Details* | Accepté |
| [0025](./0025-chaque-adaptateur-possede-son-contrat.md) | Chaque adaptateur possède son contrat : des `JsonResource` pour l'API, et des adaptateurs qui s'ignorent | Accepté |
| [0026](./0026-un-destinataire-explicite-par-token.md) | Un destinataire explicite par token, enregistré avec lui | Accepté |
| [0027](./0027-urls-annoncees-depuis-app-url.md) | Les URL que l'application annonce viennent d'`APP_URL`, jamais de la requête | Accepté |
| [0028](./0028-openapi-genere-par-scramble-verifie-par-spectator.md) | Le contrat OpenAPI est généré depuis le code par Scramble, et chaque test de l'API le vérifie avec Spectator | Accepté |
| [0029](./0029-hexagone-front-sans-domaine-duplique.md) | Un hexagone côté front, sans y recopier le domaine | Accepté |
| [0030](./0030-spa-cliente-oauth-publique.md) | Le frontend est une SPA cliente OAuth publique, et le token reste en mémoire | Accepté |
| [0031](./0031-tenter-puis-afficher-le-refus.md) | L'écran propose les actions, et c'est l'API qui refuse | Accepté, affichage complété par 0033 |
| [0032](./0032-client-http-type-par-le-contrat.md) | Le client HTTP du front est typé par le contrat OpenAPI | Accepté |
| [0033](./0033-refus-traduits-par-leur-type.md) | Le front traduit les refus par leur type, et retombe sur le message du domaine | Accepté |
| [0034](./0034-catalogue-ouvert-derriere-un-port.md) | Le catalogue ouvert est un port, et `lookup_book` un tool séparé | Accepté, complété par 0036 |
| [0035](./0035-verrou-optimiste-dans-le-repository.md) | Un verrou optimiste, tenu par le repository | Accepté |
| [0036](./0036-un-refus-est-un-type-du-projet.md) | Un refus est un type du projet, `Refusal` | Accepté |
| [0037](./0037-une-preuve-de-retrait.md) | Le repository ne retire un livre que sur preuve | Accepté |
