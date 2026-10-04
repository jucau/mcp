# ADR-0015 — `Webmozart\Assert`, jamais `assert()` natif, et c'est PHPStan qui l'impose

- **Statut** : Accepté (2026-10-04)

## Contexte

PHP propose `assert()` en natif, et PHPStan s'en sert pour affiner les types, comme il le fait avec
`Webmozart\Assert\Assert` grâce à `phpstan-webmozart-assert`. Pourtant, les deux n'ont pas la même
**sémantique à l'exécution** : `assert()` dépend de la directive `zend.assertions`. Elle vaut `1` dans notre
conteneur de dev, mais **`-1` en production**, la valeur recommandée par le `php.ini-production` de PHP. À
`-1`, l'appel est **retiré à la compilation** : l'expression n'est même pas évaluée.

Une vérification qui disparaît en production est un piège. PHPStan a déduit un type grâce à elle, mais rien ne
le garantit plus à l'exécution. Et ce qui tourne en test n'est plus ce qui tourne en production.

Jusqu'ici, la règle « jamais d'`assert()` natif » était une convention orale. Or, d'après `CLAUDE.md`, tout ce
qui est **vérifiable mécaniquement** relève de la porte qualité.

## Décision

- Les préconditions et les affinages de type passent par **`Webmozart\Assert\Assert`**, dans le code de prod
  comme dans les tests. Cette classe lève toujours une exception, quel que soit le `php.ini`.
- **`spaze/phpstan-disallowed-calls`** interdit `assert()` dans `phpstan.neon`. L'erreur renvoie à cet ADR, et
  `make stan` (donc `make quality`) échoue.

## Conséquences

- (+) La règle ne repose plus sur la mémoire de qui écrit le code, humain ou agent : la porte la fait respecter.
- (+) L'extension pourra servir à d'autres interdits vérifiables le jour où on en aura (`dd()`, `env()` hors de
  `config/`…), sans nouvel outil.
- (−) Une dépendance de dev de plus.

## Alternatives écartées

- **Garder la convention orale** : elle a déjà failli être oubliée. Une règle vérifiable que seule la vigilance
  fait respecter finit par être enfreinte.
- **Une règle PHPArkitect** : PHPArkitect contrôle les dépendances entre classes, pas les appels de fonctions.
- **Forcer `zend.assertions = 1` partout** : on rendrait `assert()` fiable dans notre environnement, mais la
  règle dépendrait de la configuration du serveur, que le code ne contrôle pas.
