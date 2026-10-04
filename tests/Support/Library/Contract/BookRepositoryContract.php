<?php

declare(strict_types=1);

namespace Tests\Support\Library\Contract;

use App\Library\Domain\Book\BookId;
use App\Library\Domain\Book\BookRepository;
use App\Library\Domain\Book\Borrower;
use App\Library\Domain\Book\DuplicateIsbn;
use App\Library\Domain\Book\Rating;
use App\Library\Domain\Book\UnknownBook;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Library\Books;
use Tests\Support\Library\Readers;

trait BookRepositoryContract
{
    abstract protected function bookRepository(): BookRepository;

    #[Test]
    public function a_saved_book_is_read_back_with_its_whole_state(): void
    {
        $repository = $this->bookRepository();
        $book = Books::aBook(isbn: '9780441172719');
        $book->startReading(new DateTimeImmutable('2026-01-10'));
        $book->abandon(new DateTimeImmutable('2026-02-01'));
        $book->rate(Rating::fromInt(2));
        $book->lendTo(Borrower::fromString('Marie'), new DateTimeImmutable('2026-03-15'));

        $repository->save($book);

        $this->assertEquals($book, $repository->get($book->reader(), $book->id()));
    }

    #[Test]
    public function a_wishlist_book_without_isbn_is_read_back_as_saved(): void
    {
        $repository = $this->bookRepository();
        $book = Books::aBook('Le Petit Prince', 'Antoine de Saint-Exupéry', owned: false);

        $repository->save($book);

        $this->assertEquals($book, $repository->get($book->reader(), $book->id()));
    }

    #[Test]
    public function an_unknown_book_cannot_be_got(): void
    {
        $this->expectException(UnknownBook::class);

        $this->bookRepository()->get(Readers::me(), BookId::generate());
    }

    #[Test]
    public function saving_again_persists_the_new_state(): void
    {
        $repository = $this->bookRepository();
        $book = Books::aBook();
        $repository->save($book);

        $book->lendTo(Borrower::fromString('Paul'), new DateTimeImmutable('2026-04-01'));
        $repository->save($book);
        $book->markAsReturned();
        $repository->save($book);

        $this->assertNull($repository->get($book->reader(), $book->id())->loan());
    }

    #[Test]
    public function a_removed_book_is_gone(): void
    {
        $repository = $this->bookRepository();
        $book = Books::aBook();
        $repository->save($book);

        $repository->remove($book->reader(), $book->id());

        $this->expectException(UnknownBook::class);

        $repository->get($book->reader(), $book->id());
    }

    #[Test]
    public function the_book_of_another_reader_is_unknown(): void
    {
        $repository = $this->bookRepository();
        $book = Books::aBook(reader: Readers::someoneElse());
        $repository->save($book);

        $this->expectException(UnknownBook::class);

        $repository->get(Readers::me(), $book->id());
    }

    #[Test]
    public function the_book_of_another_reader_cannot_be_removed(): void
    {
        $repository = $this->bookRepository();
        $book = Books::aBook(reader: Readers::someoneElse());
        $repository->save($book);

        $repository->remove(Readers::me(), $book->id());

        $this->assertEquals($book, $repository->get(Readers::someoneElse(), $book->id()));
    }

    #[Test]
    public function two_readers_may_own_the_same_isbn(): void
    {
        $repository = $this->bookRepository();
        $mine = Books::aBook(isbn: '9780441172719');
        $theirs = Books::aBook(isbn: '0-441-17271-7', reader: Readers::someoneElse());

        $repository->save($mine);
        $repository->save($theirs);

        $this->assertEquals($theirs, $repository->get(Readers::someoneElse(), $theirs->id()));
    }

    #[Test]
    public function two_books_of_one_reader_cannot_share_an_isbn(): void
    {
        $repository = $this->bookRepository();
        $repository->save(Books::aBook(isbn: '9780441172719'));

        $this->expectException(DuplicateIsbn::class);

        $repository->save(Books::aBook('Dune (poche)', isbn: '0-441-17271-7'));
    }

    #[Test]
    public function books_without_isbn_never_collide(): void
    {
        $repository = $this->bookRepository();
        $first = Books::aBook();
        $second = Books::aBook();

        $repository->save($first);
        $repository->save($second);

        $this->assertEquals($second, $repository->get($second->reader(), $second->id()));
    }

    #[Test]
    public function re_saving_a_book_does_not_trip_its_own_isbn(): void
    {
        $repository = $this->bookRepository();
        $book = Books::aBook(isbn: '9780441172719');

        $repository->save($book);
        $repository->save($book);

        $this->assertEquals($book, $repository->get($book->reader(), $book->id()));
    }
}
