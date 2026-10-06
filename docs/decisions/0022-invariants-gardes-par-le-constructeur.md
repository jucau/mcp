# ADR-0022 — Le constructeur d'un value object garde ses invariants, le constructeur nommé normalise

- **Statut** : Accepté (2026-10-06)

## Contexte

Les value objects du domaine (`Text` et ses dérivés, `BookId`, `ReaderId`, `Isbn`, `Rating`) ont un
constructeur **privé**, appelé uniquement par des constructeurs nommés (`fromString()`, `fromInt()`,
`generate()`). Jusqu'ici, c'étaient ces constructeurs nommés qui validaient, et le constructeur acceptait
n'importe quelle valeur.

Vu de l'extérieur, aucun objet invalide ne pouvait être créé : le constructeur privé ferme cette porte. Le
trou était à l'**intérieur** de la classe. Chaque constructeur nommé devait penser à valider avant
d'appeler `new self(...)`. `BookId::generate()` ne validait d'ailleurs pas : il faisait confiance à Ramsey.
Un constructeur nommé ajouté demain aurait pu oublier la validation, et rien ne l'aurait signalé.

## Décision

- **Le constructeur valide les invariants.** Tous les chemins de création passent par lui, il ne peut donc
  pas être contourné.
- **Le constructeur nommé normalise légèrement l'entrée, puis délègue** : *trim* pour un `Text`, minuscules
  pour un UUID, tirets et espaces retirés pour un ISBN. Il ne valide rien lui-même.
- **Il ne refait pas les vérifications que sa normalisation garantit déjà.** Le constructeur ne vérifie pas
  qu'un `Text` n'a pas d'espaces autour, ni qu'un id est en minuscules : aucun chemin ne peut faire échouer
  ces contrôles. Ce serait du code mort, et Infection le signalerait comme un mutant équivalent qui survit.
- **`Isbn` convertit dans son constructeur.** Le constructeur reçoit un ISBN-10 ou un ISBN-13 compact, le
  valide et le stocke en ISBN-13. La conversion ne peut pas aller dans le constructeur nommé : la clé
  d'un ISBN-10 disparaît à la conversion, il faut donc la vérifier avant. Valider puis convertir est un seul
  et même geste.
- **Une erreur rapporte ce que l'appelant a saisi.** `Isbn::fromString()` intercepte l'`InvalidIsbn` du
  constructeur et la relance avec l'entrée d'origine. Le message de refus, que lit le modèle, montre
  `"0-441-17271-0"` et non sa forme compactée.

- **Les identifiants partagent une base abstraite `Identifier`**, construite comme `Text`. Le constructeur
  valide l'UUID, `fromString()` met en minuscules, `equals()` compare. Chaque sous-classe fournit sa propre
  exception (`InvalidBookId`, `InvalidReaderId`) : ce sont deux refus différents du domaine. **`generate()`
  reste dans `BookId` seul**, car un `ReaderId` est reçu de l'extérieur et jamais généré par le domaine. Le
  constructeur est `final protected` et non privé, pour que `generate()` puisse construire depuis la
  sous-classe.
- **Pas de Shared Kernel.** Dans `ddd/`, le Shared Kernel existe parce que plusieurs contexts possèdent en
  commun un même modèle (ADR-0022 de Comptoir). Ici il n'y a qu'un context : un dossier `Shared` n'aurait
  aucun second propriétaire. `Identifier` vit donc dans `Domain/Book`, à côté de `Text`.

## Conséquences

- (+) L'invariant est écrit à un seul endroit et vaut pour tous les constructeurs nommés, présents et futurs.
- (+) Chaque constructeur nommé dit en un coup d'œil ce qu'il change à l'entrée, sans mélanger cela avec
  les règles.
- (−) La garantie s'arrête où PHP contourne le constructeur. `unserialize()`, que le double in-memory
  utilise pour copier un livre, et `ReflectionClass::newInstanceWithoutConstructor()` créent un objet sans
  l'appeler. Ces chemins ne sont pas gardés, mais ils ne recopient que des objets déjà valides.
- (−) Pour `Isbn`, un `try`/`catch` sert uniquement à remettre l'entrée d'origine dans le message.

## Alternatives écartées

- **Laisser la validation dans les constructeurs nommés** : chacun doit y penser. Un oubli passe inaperçu,
  comme `generate()` le montrait.
- **Tout faire dans le constructeur, normalisation comprise** : les constructeurs nommés deviendraient de
  simples `new self($value)`, et le constructeur modifierait en silence ce qu'on lui passe. On mélangerait de
  nouveau la mise en forme de l'entrée et la règle.
- **Vérifier aussi la forme normalisée dans le constructeur** (*trim*, minuscules) : ces contrôles ne
  peuvent jamais échouer, Infection les rejette comme mutants équivalents.
- **`generate()` dans la base `Identifier`, comme dans `ddd/`** : chaque identifiant saurait se générer,
  `ReaderId` compris. La règle « un lecteur est reçu, jamais généré » ne tiendrait plus.
- **Une seule exception `InvalidIdentifier`, avec un libellé**, comme `InvalidText` : on perdrait la
  distinction entre un id de livre invalide et un id de lecteur invalide, que les tests font aujourd'hui.
