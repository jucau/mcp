# ADR-0009 — Chaque tool déclare explicitement ses quatre annotations

- **Statut** : Accepté (2026-09-26)

## Contexte

MCP définit quatre annotations de tool : `readOnlyHint`, `destructiveHint`, `idempotentHint`, `openWorldHint`
([04](../04-les-trois-primitives.md)). Quand un hint est absent, la spec retient le cas le plus pessimiste : le tool
peut modifier, détruire, cumuler ses effets et toucher le monde extérieur.

Nos deux premiers tools n'en déclaraient aucun : `search_books`, une simple lecture, s'annonçait donc comme
destructif. Il faut aussi savoir que les clients ne gèrent pas tous les défauts de la même façon.

## Décision

- **Chaque tool déclare ses quatre hints**, avec les attributs du SDK (`IsReadOnly`, `IsDestructive`,
  `IsIdempotent`, `IsOpenWorld`), **même quand la valeur est celle par défaut**, et `false` écrit en toutes
  lettres (`#[IsOpenWorld(false)]`).
- Les hints se décident **en concevant le tool**, pas dans une passe séparée.
- Le test `it_announces_its_contract_to_the_model` de chaque tool les vérifie **à l'identique**.

## Conséquences

- (+) Le profil d'un tool se lit dans sa classe, sans connaître les défauts de la spec.
- (+) Aucun tool ne s'annonce destructif par oubli, et le test de contrat attrape un hint perdu.
- (−) Quatre attributs par tool, dont certains répètent la valeur par défaut. On l'accepte : la lisibilité compte
  plus que ces quatre lignes.

## Alternatives écartées

- **Ne déclarer que ce qui s'écarte des défauts** : `remove_book` ne porterait alors pas `IsDestructive`, puisque
  c'est déjà le défaut. Pour savoir qu'un tool détruit, il faudrait remarquer une **absence**. C'est le pire cas
  pour la lisibilité.
- **Ne rien déclarer** : chaque tool se présente comme destructif, et le host demande confirmation pour une simple
  recherche.
