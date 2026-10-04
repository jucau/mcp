<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use DateTimeImmutable;

final class Book
{
    private function __construct(
        private readonly BookId $id,
        private readonly ReaderId $reader,
        private readonly Title $title,
        private readonly Author $author,
        private readonly ?Isbn $isbn,
        private bool $owned,
        private ReadingStatus $readingStatus,
        private ?DateTimeImmutable $startedOn,
        private ?DateTimeImmutable $finishedOn,
        private ?Rating $rating,
        private ?Loan $loan,
    ) {
    }

    public static function add(BookId $id, ReaderId $reader, Title $title, Author $author, ?Isbn $isbn, bool $owned): self
    {
        return new self($id, $reader, $title, $author, $isbn, $owned, ReadingStatus::ToRead, null, null, null, null);
    }

    public static function reconstitute(
        BookId $id,
        ReaderId $reader,
        Title $title,
        Author $author,
        ?Isbn $isbn,
        bool $owned,
        ReadingStatus $readingStatus,
        ?DateTimeImmutable $startedOn,
        ?DateTimeImmutable $finishedOn,
        ?Rating $rating,
        ?Loan $loan,
    ): self {
        return new self($id, $reader, $title, $author, $isbn, $owned, $readingStatus, $startedOn, $finishedOn, $rating, $loan);
    }

    public function acquire(): void
    {
        $this->owned = true;
    }

    public function startReading(DateTimeImmutable $on): void
    {
        $this->guardReadingTransition('start reading', ReadingStatus::ToRead, ReadingStatus::Abandoned);

        $this->readingStatus = ReadingStatus::Reading;
        $this->startedOn = $on;
        $this->finishedOn = null;
        $this->rating = null;
    }

    public function finishReading(DateTimeImmutable $on): void
    {
        $this->guardReadingTransition('finish reading', ReadingStatus::ToRead, ReadingStatus::Reading);
        $this->guardNotFinishedBeforeStarted($on);

        $this->readingStatus = ReadingStatus::Read;
        $this->finishedOn = $on;
    }

    public function abandon(DateTimeImmutable $on): void
    {
        $this->guardReadingTransition('abandon', ReadingStatus::Reading);
        $this->guardNotFinishedBeforeStarted($on);

        $this->readingStatus = ReadingStatus::Abandoned;
        $this->finishedOn = $on;
    }

    public function rate(Rating $rating): void
    {
        if (! in_array($this->readingStatus, [ReadingStatus::Read, ReadingStatus::Abandoned], true)) {
            throw BookNotFinished::cannotBeRated($this->title);
        }

        $this->rating = $rating;
    }

    public function lendTo(Borrower $borrower, DateTimeImmutable $on): void
    {
        if (! $this->owned) {
            throw BookNotOwned::cannotBeLent($this->title);
        }

        if ($this->loan instanceof Loan) {
            throw BookAlreadyLent::to($this->title, $this->loan->borrower);
        }

        $this->loan = new Loan($borrower, $on);
    }

    public function markAsReturned(): void
    {
        if (! $this->loan instanceof Loan) {
            throw BookNotLent::cannotBeReturned($this->title);
        }

        $this->loan = null;
    }

    public function ensureRemovable(): void
    {
        if ($this->loan instanceof Loan) {
            throw BookLent::cannotBeRemoved($this->title, $this->loan->borrower);
        }
    }

    public function id(): BookId
    {
        return $this->id;
    }

    public function reader(): ReaderId
    {
        return $this->reader;
    }

    public function title(): Title
    {
        return $this->title;
    }

    public function author(): Author
    {
        return $this->author;
    }

    public function isbn(): ?Isbn
    {
        return $this->isbn;
    }

    public function isOwned(): bool
    {
        return $this->owned;
    }

    public function readingStatus(): ReadingStatus
    {
        return $this->readingStatus;
    }

    public function startedOn(): ?DateTimeImmutable
    {
        return $this->startedOn;
    }

    public function finishedOn(): ?DateTimeImmutable
    {
        return $this->finishedOn;
    }

    public function rating(): ?Rating
    {
        return $this->rating;
    }

    public function loan(): ?Loan
    {
        return $this->loan;
    }

    private function guardReadingTransition(string $action, ReadingStatus ...$allowedFrom): void
    {
        if (! in_array($this->readingStatus, $allowedFrom, true)) {
            throw ReadingTransitionNotAllowed::from($this->title, $this->readingStatus, $action);
        }
    }

    private function guardNotFinishedBeforeStarted(DateTimeImmutable $on): void
    {
        if ($this->startedOn instanceof DateTimeImmutable && $on < $this->startedOn) {
            throw FinishedBeforeStarted::on($this->title, $on, $this->startedOn);
        }
    }
}
