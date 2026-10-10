# ADR-0032 — Le client HTTP du front est typé par le contrat OpenAPI

- **Statut** : Accepté (2026-10-10)

## Contexte

L'API publie un contrat OpenAPI 3.1 généré depuis son code
([ADR-0028](./0028-openapi-genere-par-scramble-verifie-par-spectator.md)). Le front doit parler à cette API, et un
client écrit à la main finirait par diverger d'elle sans que personne ne le voie avant l'exécution.

## Décision

- **openapi-typescript génère les types** du client depuis `docs/api/openapi.json`, dans la porte, juste après
  l'export du contrat. Le fichier généré est versionné, comme le contrat.
- **openapi-fetch** les utilise : chemins, paramètres, corps et réponses sont typés.
- **Seul l'adaptateur HTTP** voit ces types : il traduit les réponses vers le modèle du cœur, et les *Problem Details*
  en refus. Le cœur ne dépend pas du contrat de l'API, il définit le sien.
- **TypeScript reste en 5.9**, la plus récente version que typescript-eslint et openapi-typescript acceptent.
  TypeScript 7 attendra leur support.

## Conséquences

- (+) Une dérive entre l'API et le front casse la compilation, dans la même porte que celle qui génère le contrat.
- (+) Un renommage dans l'API se propage par le compilateur, pas par une recherche dans le code.
- (−) Une traduction de plus, dans l'adaptateur, entre le type du contrat et le modèle du cœur.
- (−) Le front dépend de la qualité du contrat généré : ce que Scramble documente mal, le front le type mal.

## Alternatives écartées

- **Des types écrits à la main** : rien ne les relierait à l'API.
- **Un SDK généré complet** (classes et méthodes par opération) : plus lourd, et il imposerait sa forme au cœur.
- **Le contrat directement dans le cœur** : le cœur dépendrait de l'API, c'est l'inverse de l'hexagone.
