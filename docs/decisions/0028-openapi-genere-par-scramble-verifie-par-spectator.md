# ADR-0028 — Le contrat OpenAPI est généré depuis le code par Scramble, et chaque test de l'API le vérifie avec Spectator

- **Statut** : Accepté (2026-10-10)

## Contexte

L'[ADR-0025](./0025-chaque-adaptateur-possede-son-contrat.md) donne à l'API un contrat OpenAPI versionné, vérifié par
les tests, comme l'`outputSchema` des tools ([ADR-0013](./0013-output-schema-verifie-par-les-tests.md)). Restait à
décider s'il s'écrit à la main ou se génère. Générer un contrat depuis le code est un besoin récurrent, qu'on veut
savoir faire, à condition de pouvoir reprendre la main.

Une veille (octobre 2026) a comparé les outils, puis un essai les a confrontés à notre API ([09](../09-api-rest.md)) :

- **Scribe** obtient ses réponses en exécutant les endpoints, ou en instanciant une resource sur un modèle Eloquent
  fabriqué par une factory. Nos `JsonResource` présentent un `BookView`, pas un modèle : l'hexagone ne lui donne
  aucune prise. Les schémas sont déduits d'exemples, pas des types.
- **Scramble** analyse le code sans l'exécuter : la validation en ligne, les lectures de la query string, le
  `toArray()` des resources, la pagination, le `201` et le `204`. Il produit de l'OpenAPI 3.1 et offre des prises
  étagées : attributs, PHPDoc (`@throws`, `@response`), *transformers* d'opération et de document, extensions.
- **swagger-php / L5-Swagger** fait écrire le contrat dans des attributs : c'est de la rédaction, pas de la génération.
- Pour la vérification, **`league/openapi-psr7-validator`** ne lit que l'OpenAPI 3.0. **Spectator** lit notre 3.1,
  valide requêtes et réponses depuis les tests Laravel, et l'essai a montré qu'il attrape un champ mal typé, un statut
  non documenté et une requête hors contrat.

Scramble ne voit pas les refus du domaine, levés au fond des handlers. Il lit les `@throws` sur un seul niveau : le
contrôleur ou le handler qu'il appelle, pas le domaine.

## Décision

- **Scramble génère le contrat** (`make openapi`, dans la porte), exporté et versionné dans `docs/api/openapi.json` :
  un changement de l'API se voit dans le diff.
- **Les refus et la validation sont documentés en *Problem Details*** par deux extensions qui reprennent la table de
  `ProblemDetails` : le statut, le `type` et le `title` de la réponse réelle et du contrat viennent des mêmes fonctions.
  Le `type`, le `title` et le `status` d'un refus y sont des valeurs imposées, pas des exemples.
- **Chaque contrôleur déclare ses refus par `@throws`.** C'est l'adaptateur qui déclare son contrat, le cœur ne
  bouge pas.
- **Un paramètre de query n'est jamais `null`** dans le contrat : un *transformer* retire le `null` que Scramble tire
  du `nullable` de Laravel.
- **La sécurité OAuth2** (code d'autorisation, PKCE) est déclarée par un *transformer* de document.
- **Chaque test de l'API passe par Spectator** : toute réponse doit respecter le contrat, et toute requête que l'API
  accepte aussi. Une requête refusée exprès par un test sort du contrat par construction.
- Scramble et Spectator sont des dépendances **de dev** : seul l'export charge les extensions.

## Conséquences

- (+) Le contrat suit le code sans qu'on le réécrive, et les tests empêchent l'un de dériver de l'autre.
- (+) Un `@throws` oublié fait échouer le test qui provoque ce refus, même quand un autre refus partage son statut.
- (+) Un test qui enverrait une requête que le contrat ne décrit pas échoue : les tests restent dans le contrat.
- (−) Un refus qu'aucun test ne provoque peut manquer au contrat sans que rien ne le signale.
- (−) Scramble est en 0.x : son API d'extension peut changer avant la version stable.
- (−) Les `@throws` des contrôleurs répètent ce que savent les handlers et le domaine.
- (−) L'export émet des avertissements sans effet sur le document : il ne sait pas instancier `CurrentReader` (fermé
  par défaut, [ADR-0021](./0021-chaque-transport-choisit-son-lecteur.md)), ni trouver de modèle derrière `BookResource`.

## Alternatives écartées

- **Écrire le contrat à la main** : fidèle à « la doc fait foi », mais on voulait apprendre à générer, et les tests
  rattrapent la dérive dans les deux sens.
- **Scribe** : il demande un modèle Eloquent ou des appels réels pour connaître une réponse.
- **Les `@throws` sur les handlers** : une vérité sur le use case, mais `Application` changerait pour un adaptateur, et
  il faudrait la vérification des exceptions de PHPStan pour qu'ils ne dérivent pas, avec des `@throws` en cascade
  jusqu'au domaine.
- **Un validateur maison** sur `justinrainbow/json-schema` : possible, puisqu'un schéma OpenAPI 3.1 est du JSON
  Schema, mais Spectator fait davantage (requêtes, statuts non documentés) et il est maintenu.
