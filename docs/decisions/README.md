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
| [0008](./0008-tools-adaptateurs-minces-refus-metier.md) | Les tools sont des adaptateurs minces qui transmettent les refus métier | Accepté |
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
| [0020](./0020-passport-serveur-d-autorisation-destinataire-implicite.md) | Passport comme serveur d'autorisation, et un destinataire de token implicite | Accepté |
| [0021](./0021-chaque-transport-choisit-son-lecteur.md) | Chaque transport choisit son lecteur, et aucun lecteur n'est servi par défaut | Accepté |
| [0022](./0022-invariants-gardes-par-le-constructeur.md) | Le constructeur d'un value object garde ses invariants, le constructeur nommé normalise | Accepté |
| [0023](./0023-completer-seulement-ce-qu-un-humain-choisit.md) | Ne compléter que ce qu'un humain sait choisir, à partir des données du lecteur | Accepté |
