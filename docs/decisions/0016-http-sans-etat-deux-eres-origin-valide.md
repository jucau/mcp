# ADR-0016 — Un endpoint HTTP sans état, qui sert les deux époques du protocole et valide l'`Origin`

- **Statut** : Accepté (2026-10-04)

## Contexte

La version `2026-07-28` de la spec MCP a rendu le protocole sans état : plus d'`initialize`, plus de
`Mcp-Session-Id`, et chaque requête porte sa version et les capacités du client dans `_meta`. Elle appelle
*legacy* les versions jusqu'à `2025-11-25`, qui ouvrent une session par `initialize`. Elle autorise un serveur
*dual-era*, qui sert les deux.

`laravel/mcp` v1.0.1 est déjà ce serveur dual-era. Il ne délivre aucune session HTTP. Il vérifie les en-têtes MCP
(`MCP-Protocol-Version`, `Mcp-Method`, `Mcp-Name`) des requêtes modernes. Il répond `405` aux `GET` et `DELETE`.
En revanche, il **ne valide pas l'en-tête `Origin`**, alors que la spec l'exige pour parer le DNS rebinding.

Enfin, notre `docker-compose.yml` publie nginx sur toutes les interfaces de l'hôte, alors que la spec recommande
qu'un serveur local n'écoute que sur `127.0.0.1`.

## Décision

- Le serveur est enregistré **deux fois**, sous les mêmes primitives : `Mcp::local('library', …)` pour stdio et
  `Mcp::web('/mcp', …)` pour HTTP. Aucun tool, resource ou prompt ne sait par quel transport il est appelé.
- **Les deux époques sont servies**, telles que `laravel/mcp` les sert. On ne réécrit pas le transport du SDK.
- **Aucun état de session côté serveur**, ni en legacy ni en moderne. Si un état doit un jour survivre d'un appel à
  l'autre, il passera par un identifiant émis par le serveur et repassé comme argument de tool, comme le prévoit
  la spec.
- **Un middleware à nous valide l'`Origin`** sur la route MCP. Un `Origin` absent est accepté : les clients hors
  navigateur n'en envoient pas. Un `Origin` présent doit figurer dans une liste autorisée, issue de la config et
  qui contient par défaut `APP_URL`. Sinon, la réponse est `403`.
- **nginx n'est publié que sur `127.0.0.1`.**
- Les comportements HTTP qu'on attend du serveur (`202`, `405`, `400`/`-32020`, `400`/`-32022`, `403`) sont
  **épinglés par des tests Feature**. Comme avec l'`outputSchema` ([ADR-0013](./0013-output-schema-verifie-par-les-tests.md)),
  on vérifie ce qui circule réellement, pas ce que le SDK promet.

## Conséquences

- (+) Le serveur HTTP peut tourner en plusieurs instances derrière un load balancer, sans session partagée.
- (+) La faille de DNS rebinding est fermée, et un test le prouve.
- (+) La comparaison des transports porte sur trois cas réels : stdio, HTTP legacy et HTTP moderne.
- (−) On dépend du SDK pour le respect de la spec qu'on ne teste pas nous-mêmes (le flux SSE, l'encodage base64
  de `Mcp-Name`).
- (−) Ce que fait un client legacy qui comptait sur une session dépend de lui : `2025-11-25` rendait la session
  facultative, et un client conforme doit s'en passer.

## Alternatives écartées

- **Ne servir que la version moderne**, en rejetant `initialize` : on prendrait de l'avance sur des clients qui
  ne la parlent peut-être pas encore. Mieux vaut vérifier d'abord ce que Claude Code négocie, à la troisième
  étape de la roadmap.
- **Attribuer un `Mcp-Session-Id` aux clients legacy** : il faudrait réimplémenter une partie du transport du SDK,
  pour une session que la spec a supprimée.
- **Valider l'`Origin` dans nginx** : c'est possible, mais la règle sortirait du code testé par PHPUnit, et le
  `403` ne porterait pas l'erreur JSON-RPC que la spec permet.
