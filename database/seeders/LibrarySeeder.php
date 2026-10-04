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
    public const string JULIEN = '6698aec1-1185-4a24-bcff-f5d935324b3a';

    public const string CAMILLE = '1f310160-f25a-46bf-b5fd-02f333a645c4';

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
        $this->julien();
        $this->camille();
    }

    private function julien(): void
    {
        $reader = self::JULIEN;

        $this->read($reader, 'Dune', 'Frank Herbert', '9780441172719', '2025-01-04', '2025-02-10', 5);
        $this->read($reader, "L'Étranger", 'Albert Camus', '9782070360024', '2025-03-01', '2025-03-06', 4);
        $this->read($reader, 'Fondation', 'Isaac Asimov', null, '2025-04-12', '2025-05-02', 4);
        $this->read($reader, 'Le Seigneur des anneaux', 'J. R. R. Tolkien', null, '2025-06-01', '2025-08-15', 5, lentTo: 'Paul', lentOn: '2026-07-03');
        $this->read($reader, '1984', 'George Orwell', null, '2025-09-03', '2025-09-20', 5, lentTo: 'Marie', lentOn: '2026-08-20');
        $this->read($reader, 'La Horde du Contrevent', 'Alain Damasio', null, '2025-10-01', '2025-11-18', 5);
        $this->read($reader, 'Le Nom de la rose', 'Umberto Eco', null, '2025-12-02', '2026-01-20', 4);
        $this->read($reader, 'Sapiens', 'Yuval Noah Harari', null, '2026-02-01', '2026-03-10', 3);
        $this->read($reader, 'Les Misérables', 'Victor Hugo', null, null, '2012-07-14', 4);

        $this->reading($reader, 'Hypérion', 'Dan Simmons', '2026-09-02');
        $this->reading($reader, 'Le Problème à trois corps', 'Liu Cixin', '2026-09-15');

        $this->abandoned($reader, 'Ulysse', 'James Joyce', '2026-04-01', '2026-04-19', 2);
        $this->abandoned($reader, 'Le Capital au XXIe siècle', 'Thomas Piketty', '2026-05-05', '2026-06-01', null);

        $this->toRead($reader, 'La Main gauche de la nuit', 'Ursula K. Le Guin');
        $this->toRead($reader, 'Chroniques martiennes', 'Ray Bradbury');
        $this->toRead($reader, "L'Anomalie", 'Hervé Le Tellier');
        $this->toRead($reader, 'Cent ans de solitude', 'Gabriel García Márquez');
        $this->toRead($reader, 'Les Furtifs', 'Alain Damasio');
        $neuromancer = $this->toRead($reader, 'Neuromancien', 'William Gibson');
        $this->lendBook->handle(new LendBook($reader, $neuromancer, 'Léa', new DateTimeImmutable('2026-09-01')));

        foreach ([
            ['Le Sorceleur', 'Andrzej Sapkowski'],
            ['Klara et le Soleil', 'Kazuo Ishiguro'],
            ['Projet Dernière Chance', 'Andy Weir'],
            ["La Vérité sur l'affaire Harry Quebert", 'Joël Dicker'],
        ] as [$title, $author]) {
            $this->addBook->handle(new AddBook($reader, $title, $author, owned: false));
        }
    }

    private function camille(): void
    {
        $reader = self::CAMILLE;

        $this->read($reader, 'Dune', 'Frank Herbert', '9780441172719', '2024-06-01', '2024-07-02', 3);
        $this->read($reader, 'Le Seigneur des anneaux', 'J. R. R. Tolkien', null, '2025-01-10', '2025-04-30', 5, lentTo: 'Julien', lentOn: '2026-09-10');
        $this->reading($reader, 'Fondation', 'Isaac Asimov', '2026-09-20');
        $this->toRead($reader, 'Hypérion', 'Dan Simmons');
        $this->addBook->handle(new AddBook($reader, 'La Horde du Contrevent', 'Alain Damasio', owned: false));
    }

    private function toRead(string $reader, string $title, string $author, ?string $isbn = null): string
    {
        return $this->addBook->handle(new AddBook($reader, $title, $author, $isbn))->value;
    }

    private function reading(string $reader, string $title, string $author, string $startedOn): string
    {
        $id = $this->toRead($reader, $title, $author);
        $this->startReading->handle(new StartReading($reader, $id, new DateTimeImmutable($startedOn)));

        return $id;
    }

    private function read(
        string $reader,
        string $title,
        string $author,
        ?string $isbn,
        ?string $startedOn,
        string $finishedOn,
        int $rating,
        ?string $lentTo = null,
        ?string $lentOn = null,
    ): void {
        $id = $this->toRead($reader, $title, $author, $isbn);

        if ($startedOn !== null) {
            $this->startReading->handle(new StartReading($reader, $id, new DateTimeImmutable($startedOn)));
        }

        $this->finishReading->handle(new FinishReading($reader, $id, new DateTimeImmutable($finishedOn)));
        $this->rateBook->handle(new RateBook($reader, $id, $rating));

        if ($lentTo !== null && $lentOn !== null) {
            $this->lendBook->handle(new LendBook($reader, $id, $lentTo, new DateTimeImmutable($lentOn)));
        }
    }

    private function abandoned(string $reader, string $title, string $author, string $startedOn, string $abandonedOn, ?int $rating): void
    {
        $id = $this->reading($reader, $title, $author, $startedOn);
        $this->abandonReading->handle(new AbandonReading($reader, $id, new DateTimeImmutable($abandonedOn)));

        if ($rating !== null) {
            $this->rateBook->handle(new RateBook($reader, $id, $rating));
        }
    }
}
