# ADR-0010 — Les instants en UTC, les jours dans le fuseau du lecteur

- **Statut** : Accepté (2026-09-27)

## Contexte

L'application tourne en UTC (`app.timezone`), comme il se doit : c'est ce qui date les timestamps (`created_at`,
les logs, les files d'attente), et l'UTC évite toute ambiguïté au changement d'heure.

Mais `started_on` et `finished_on` ne sont pas des instants. Ce sont des **jours du calendrier** : « le jour où
j'ai fini Dune ». Un jour n'a de sens que dans le fuseau de celui qui le vit. À 00 h 30 à Paris, le 27, l'UTC en
est encore au 26 : un « aujourd'hui » calculé en UTC daterait la lecture de la veille.

## Décision

- **Tout instant est stocké en UTC ; une colonne `date` ne porte pas de fuseau.** Le fuseau du lecteur ne sert
  qu'à choisir **quel jour** écrire. Rien n'est jamais stocké en heure locale.
- **`app.timezone` reste en UTC.**
- **« Aujourd'hui » se calcule dans le fuseau du lecteur**, par `SystemClock`, l'adaptateur du port `Clock`.
  Le fuseau vient de `config('library.reader_timezone')` (`LIBRARY_READER_TIMEZONE`, `Europe/Paris` dans
  `.env.example`).
- **Le jour renvoyé est à minuit dans le fuseau de l'application**, comme toute date relue depuis la base. Un
  minuit à Paris tomberait la veille en UTC, et le domaine, qui compare des instants, refuserait de finir un
  livre commencé le jour même. Ce minuit n'existe qu'en mémoire : PHP n'a pas de type « date seule », et seul
  le jour (`2026-09-27`) est écrit en base.

## Conséquences

- (+) Le domaine et les use cases ne changent pas : la règle vit dans l'adaptateur du port `Clock`.
- (+) À l'étape 5 (plusieurs utilisateurs), le fuseau deviendra une donnée de chaque lecteur, lue au même endroit.
- (−) Un seul fuseau pour toute la bibliothèque d'ici là.

## Alternatives écartées

- **Passer `app.timezone` à `Europe/Paris`** : on changerait tous les instants de l'application pour corriger
  une seule notion, celle du jour. Ça ne tiendrait plus avec plusieurs lecteurs.
- **Laisser le modèle fournir la date** : il ne la connaît pas de façon fiable
  ([04](../04-les-trois-primitives.md)).
