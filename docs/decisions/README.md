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
