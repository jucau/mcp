# ADR-0003 — Un domaine autonome : la bibliothèque personnelle

- **Statut** : Accepté (2026-09-26)

## Contexte

Le serveur MCP a besoin d'un métier à exposer. Trois pistes : brancher Comptoir (`ddd/`), brancher le
RAG Football (`rag/`), ou partir d'un domaine neuf. Le domaine doit faire vivre les **trois primitives**
(tools en lecture **et** en écriture, resources, prompts) sans voler la vedette au protocole.

## Décision

Un projet **autonome**, sur le domaine d'une **bibliothèque personnelle** : livres, lectures (à lire / en
cours / lu), notes, prêts à des amis.

## Conséquences

- (+) Domaine universellement compris : zéro effort de modélisation métier à expliquer.
- (+) Il couvre naturellement les trois primitives : `search_books` (lecture), `lend_book` (écriture,
  non idempotente), `library://shelves/to-read` (resource), « recommande-moi ma prochaine lecture » (prompt).
- (+) Aucune dépendance aux autres projets : on peut casser, recommencer, déployer seul.
- (−) Moins réaliste qu'un vrai back-office : pas d'architecture hexagonale à respecter, peu d'invariants.
  Assumé : le sujet est MCP, pas le DDD.

## Alternatives écartées

- **Comptoir (`ddd/`)** : un serveur MCP y serait un bel adaptateur *driving* de l'hexagone, mais on
  apprendrait MCP au milieu d'un modèle déjà riche et de règles d'architecture strictes.
- **RAG Football (`rag/`)** : montre bien RAG vs MCP, mais ne fournit presque que de la lecture ; pas
  d'écriture, peu de matière pour les resources et les prompts.
