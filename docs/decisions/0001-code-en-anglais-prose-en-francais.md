# ADR-0001 — Code en anglais, prose en français

- **Statut** : Accepté (2026-09-26)

## Contexte

Le projet est un support d'apprentissage : la doc doit se lire sans effort, en français. Mais le code, lui,
est lu par des outils et par des **modèles** : les noms de tools, leurs descriptions et leurs paramètres sont
exactement ce que le LLM voit pour décider de les appeler. Mélanger les langues (`chercherBook`,
`lend_livre`) brouille à la fois la lecture humaine et le choix du modèle.

## Décision

- Le **code** est en **anglais** : types, méthodes, tables, **noms et descriptions** des tools, resources et
  prompts MCP.
- La **prose** des documents (`docs/`) est en **français** ; les termes qui y sont cités sont les termes
  anglais du code.
- Les termes de la **spécification MCP** gardent leur nom officiel (*tool*, *resource*, *prompt*,
  *transport*, *capability*…).
- Le franglais est proscrit.

## Conséquences

- (+) Le code s'aligne sur la spec MCP et sur le SDK, eux-mêmes en anglais.
- (+) Les descriptions de tools sont dans la langue où les modèles sont le plus robustes.
- (−) Une traduction mentale FR (réflexion) ↔ EN (code) — jugée acceptable.

## Alternatives écartées

- **Tout en français** : écarté, les noms du SDK et de la spec sont anglais ; on obtiendrait du franglais.
- **Descriptions de tools en français** : tentant pour un utilisateur francophone, mais le modèle comprend
  l'utilisateur quelle que soit sa langue ; ce sont les *noms* qui doivent être stables et non ambigus.
