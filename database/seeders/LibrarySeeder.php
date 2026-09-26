<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Library\Application\Book\AbandonReading\AbandonReading;
use App\Library\Application\Book\AbandonReading\AbandonReadingHandler;
use App\Library\Application\Book\AddBook\AddBook;
use App\Library\Application\Book\AddBook\AddBookHandler;
use App\Library\Application\Book\FinishReading\FinishReading;
use App\Library\Application\Book\FinishReading\FinishReadingHandler;
use App\Library\Application\Book\LendBook\LendBook;
use App\Library\Application\Book\LendBook\LendBookHandler;
use App\Library\Application\Book\RateBook\RateBook;
use App\Library\Application\Book\RateBook\RateBookHandler;
use App\Library\Application\Book\StartReading\StartReading;
use App\Library\Application\Book\StartReading\StartReadingHandler;
use DateTimeImmutable;
use Illuminate\Database\Seeder;

final class LibrarySeeder extends Seeder
{
    public function __construct(
        private readonly AddBookHandler $addBook,
        private readonly StartReadingHandler $startReading,
        private readonly FinishReadingHandler $finishReading,
        private readonly AbandonReadingHandler $abandonReading,
        private readonly RateBookHandler $rateBook,
        private readonly LendBookHandler $lendBook,
    ) {
    }

    public function run(): void
    {
        $this->read('Dune', 'Frank Herbert', '9780441172719', '2025-01-04', '2025-02-10', 5);
        $this->read("L'Étranger", 'Albert Camus', '9782070360024', '2025-03-01', '2025-03-06', 4);
        $this->read('Fondation', 'Isaac Asimov', null, '2025-04-12', '2025-05-02', 4);
        $this->read('Le Seigneur des anneaux', 'J. R. R. Tolkien', null, '2025-06-01', '2025-08-15', 5, lentTo: 'Paul', lentOn: '2026-07-03');
        $this->read('1984', 'George Orwell', null, '2025-09-03', '2025-09-20', 5, lentTo: 'Marie', lentOn: '2026-08-20');
        $this->read('La Horde du Contrevent', 'Alain Damasio', null, '2025-10-01', '2025-11-18', 5);
        $this->read('Le Nom de la rose', 'Umberto Eco', null, '2025-12-02', '2026-01-20', 4);
        $this->read('Sapiens', 'Yuval Noah Harari', null, '2026-02-01', '2026-03-10', 3);
        $this->read('Les Misérables', 'Victor Hugo', null, null, '2012-07-14', 4);

        $this->reading('Hypérion', 'Dan Simmons', '2026-09-02');
        $this->reading('Le Problème à trois corps', 'Liu Cixin', '2026-09-15');

        $this->abandoned('Ulysse', 'James Joyce', '2026-04-01', '2026-04-19', 2);
        $this->abandoned('Le Capital au XXIe siècle', 'Thomas Piketty', '2026-05-05', '2026-06-01', null);

        $this->toRead('La Main gauche de la nuit', 'Ursula K. Le Guin');
        $this->toRead('Chroniques martiennes', 'Ray Bradbury');
        $this->toRead("L'Anomalie", 'Hervé Le Tellier');
        $this->toRead('Cent ans de solitude', 'Gabriel García Márquez');
        $this->toRead('Les Furtifs', 'Alain Damasio');
        $neuromancer = $this->toRead('Neuromancien', 'William Gibson');
        $this->lendBook->handle(new LendBook($neuromancer, 'Léa', new DateTimeImmutable('2026-09-01')));

        foreach ([
            ['Le Sorceleur', 'Andrzej Sapkowski'],
            ['Klara et le Soleil', 'Kazuo Ishiguro'],
            ['Projet Dernière Chance', 'Andy Weir'],
            ["La Vérité sur l'affaire Harry Quebert", 'Joël Dicker'],
        ] as [$title, $author]) {
            $this->addBook->handle(new AddBook($title, $author, owned: false));
        }
    }

    private function toRead(string $title, string $author, ?string $isbn = null): string
    {
        return $this->addBook->handle(new AddBook($title, $author, $isbn))->value;
    }

    private function reading(string $title, string $author, string $startedOn): string
    {
        $id = $this->toRead($title, $author);
        $this->startReading->handle(new StartReading($id, new DateTimeImmutable($startedOn)));

        return $id;
    }

    private function read(
        string $title,
        string $author,
        ?string $isbn,
        ?string $startedOn,
        string $finishedOn,
        int $rating,
        ?string $lentTo = null,
        ?string $lentOn = null,
    ): void {
        $id = $this->toRead($title, $author, $isbn);

        if ($startedOn !== null) {
            $this->startReading->handle(new StartReading($id, new DateTimeImmutable($startedOn)));
        }

        $this->finishReading->handle(new FinishReading($id, new DateTimeImmutable($finishedOn)));
        $this->rateBook->handle(new RateBook($id, $rating));

        if ($lentTo !== null && $lentOn !== null) {
            $this->lendBook->handle(new LendBook($id, $lentTo, new DateTimeImmutable($lentOn)));
        }
    }

    private function abandoned(string $title, string $author, string $startedOn, string $abandonedOn, ?int $rating): void
    {
        $id = $this->reading($title, $author, $startedOn);
        $this->abandonReading->handle(new AbandonReading($id, new DateTimeImmutable($abandonedOn)));

        if ($rating !== null) {
            $this->rateBook->handle(new RateBook($id, $rating));
        }
    }
}
