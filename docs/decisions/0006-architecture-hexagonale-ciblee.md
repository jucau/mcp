# ADR-0006 — Architecture hexagonale ciblée, MCP comme adaptateur primaire

- **Statut** : Accepté (2026-09-26)
- **Nuance** : [ADR-0003](./0003-domaine-autonome-bibliotheque-perso.md), dont une conséquence annonçait
  « pas d'architecture hexagonale à respecter ».

## Contexte

L'erreur la plus répandue dans les serveurs MCP est de mettre la logique métier **dans le tool** : le
`handle()` lit la base, applique les règles, écrit. Le jour où il faut la même capacité en HTTP ou en CLI, on
la duplique ; le jour où l'on teste, il faut un client MCP.

En architecture hexagonale, un tool MCP est exactement ce qu'est un contrôleur : un **adaptateur primaire**
(*driving*), qui traduit une entrée, appelle un cas d'usage et traduit le résultat. Les conventions de
`ddd/` sont maîtrisées ; les reprendre en entier serait disproportionné pour un seul context sans
intégration.

## Décision

On reprend de `ddd/` **ce qui structure un context**, et rien de ce qui sert l'intégration entre contexts :

- **Couches** : `app/Library/{Domain,Application,Infrastructure}`, namespace `App\Library\…`.
- **Domaine pur**, vérifié par **PHPArkitect** : `Domain` ne dépend ni de `Illuminate` ni des autres couches ;
  `Application` ne dépend ni d'`Infrastructure`, ni de `Illuminate\Database`, ni des façades. Libs de
  valeur pures autorisées dans le domaine (`ramsey/uuid`).
  **Précisé le 2026-10-11** : pour le domaine, la règle est une **liste blanche**. Il ne dépend que de lui-même, de
  PHP et de `Ramsey\Uuid`. Une interdiction laissait passer toute bibliothèque qu'elle ne nommait pas ; une
  nouvelle dépendance du domaine demande maintenant une décision, et une ligne dans `phparkitect.php`.
  La couche application suit la même règle : elle ne dépend que du domaine, d'elle-même, de PHP et de
  `Webmozart\Assert` ([ADR-0015](./0015-webmozart-assert-jamais-assert-natif.md)). `Illuminate\Support`, que
  l'ancienne règle laissait ouvert, ne l'est plus : rien ne s'en servait.
  Ces règles s'écrivent **une fois par bounded context**, listé dans `phparkitect.php`, jamais avec un joker : le
  domaine d'un contexte ne peut pas dépendre de celui d'un autre, et une classe hors des contextes listés fait
  échouer la porte.
- **Package par agrégat** : `Domain/Book/` contient la racine, ses VO, ses erreurs et son port repository.
- **Identité** : UUID de domaine (`BookId`) + PK technique `BIGINT` auto-incrémentée qui ne fuit jamais.
- **Use cases en vertical slices** : un dossier par use case (command + handler), lectures regroupées dans
  `Query/`. **Pas de command bus** : l'adaptateur injecte le handler et appelle `handle()`.
- **Persistance** : port `BookRepository` dans le domaine ; adaptateur Eloquent en `Infrastructure` ; double
  **in-memory** pour les tests. Les deux jouent le **même trait de contrat**.
- **Tests classicistes** : doubles d'état, jamais de mocks ; on asserte l'effet observable.
- **Adaptateurs primaires** (à venir) : le serveur MCP d'abord (étape 2), puis HTTP ou CLI si un besoin
  apparaît. Ils vivent en `Infrastructure`.

Écarté de `ddd/` faute de besoin : plusieurs contexts et leur matrice d'autonomie, shared kernel et contrats
publiés, domain events, outbox, Doctrine, verrou optimiste. Infection pourra venir plus tard.

## Conséquences

- (+) Le tool MCP est **mince** et le métier **testable sans MCP** : c'est la leçon centrale du projet.
- (+) Le passage stdio → HTTP (étape 4) et l'ajout éventuel d'un autre adaptateur ne touchent pas au cœur.
- (+) Une seule façon de faire entre `ddd/` et ce projet.
- (−) Plus de fichiers qu'un Laravel « classique » pour un domaine de cette taille (VO, ports, doubles).
  Assumé : c'est le prix de la démonstration.
- (−) PHPArkitect entre dans la porte qualité.

## Alternatives écartées

- **Eloquent + Actions** : plus léger, mais la frontière entre métier et adaptateur MCP ne serait tenue que
  par la discipline, sans règle vérifiée.
- **Tout l'arsenal de `ddd/`** : répond à des problèmes (intégration, cohérence entre agrégats) que ce projet
  n'a pas.
