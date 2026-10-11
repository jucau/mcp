# Marque-page — Documentation

> Projet pédagogique : apprendre le **Model Context Protocol (MCP)** en construisant un **serveur MCP**
> sur **Laravel** (`laravel/mcp`). Cette documentation est la source de vérité : le code s'y conforme,
> jamais l'inverse.

## C'est quoi « Marque-page » ?

Une **bibliothèque personnelle** — les livres qu'on possède, qu'on lit, qu'on a lus, qu'on a prêtés —
pilotable **en langage naturel depuis un agent IA** (Claude Code, Claude Desktop…) grâce à un serveur MCP.

Le domaine est volontairement **petit et familier** : l'objet d'étude est le **protocole**, pas le métier.
Il est juste assez riche pour faire vivre les trois primitives MCP :

- des **tools** qui lisent (*chercher un livre*) et qui écrivent (*prêter un livre*) ;
- des **resources** consultables (*mon étagère « à lire »*) ;
- des **prompts** réutilisables (*« recommande-moi ma prochaine lecture »*).

## Comment lire cette doc

| # | Fichier | Question à laquelle il répond |
|---|---------|-------------------------------|
| 00 | [MCP en bref](./00-mcp-en-bref.md) | C'est quoi MCP, à quoi ça sert, quels sont ses concepts ? |
| 01 | [Le domaine `Library`](./01-domaine.md) | Quel est le modèle, quels invariants protège-t-il, quels cas d'usage ? |
| 02 | [Langage ubiquitaire](./02-langage-ubiquitaire.md) | Quel mot veut dire quoi, en français et dans le code ? |
| 03 | [Premier serveur MCP](./03-premier-serveur-mcp.md) | Comment un tool est-il exposé, appelé, testé ? Que circule-t-il vraiment ? |
| 04 | [Les trois primitives](./04-les-trois-primitives.md) | Qui contrôle quoi ? Que disent les annotations d'un tool ? |
| 05 | [Le protocole à nu](./05-le-protocole-a-nu.md) | Que circule-t-il vraiment entre un client et le serveur, sans SDK ? |
| 06 | [Le transport Streamable HTTP](./06-transport-http.md) | Que change HTTP, et que change un protocole sans état ? |
| 07 | [L'authentification](./07-authentification.md) | Comment un client obtient un token, et comment un token devient un lecteur ? |
| 08 | [Les complétions d'arguments](./08-completions.md) | À qui sert la complétion, et quoi compléter ? |
| 09 | [Un second adaptateur : l'API REST](./09-api-rest.md) | Le cœur tient-il face à un autre adaptateur, et à qui est destiné un token ? |
| 10 | [Le frontend](./10-frontend.md) | Que fait un client OAuth public, et qu'est-ce qu'un hexagone sans domaine ? |
| 11 | [Sortir du monde fermé](./11-monde-ouvert.md) | Que change un tool qui interroge un service tiers, et que dit `openWorldHint` ? |
| — | [Roadmap](./roadmap.md) | Dans quel ordre construit-on, et qu'apprend-on à chaque étape ? |
| — | [Décisions (ADR)](./decisions/README.md) | Pourquoi c'est fait comme ça ? |

Les documents de chaque **étape MCP** s'ajouteront ici au fil de la [roadmap](./roadmap.md), **avant** le code
qu'ils décrivent.

## Conventions de cette doc

- La **prose** est en **français** (apprentissage). Le **code** est en **anglais** : types, méthodes, tables,
  noms de tools/resources/prompts ([ADR-0001](./decisions/0001-code-en-anglais-prose-en-francais.md)).
- Les termes MCP gardent leur nom officiel anglais (*tool*, *resource*, *prompt*, *transport*…) : ce sont
  ceux de la spécification et du SDK.
