<?php

declare(strict_types=1);

namespace Tests\Support\Library\Contract;

use App\Library\Domain\Book\Book;
use App\Library\Domain\Book\BookChangedMeanwhile;
use App\Library\Domain\Book\BookId;
use App\Library\Domain\Book\BookRepository;
use App\Library\Domain\Book\Borrower;
use App\Library\Domain\Book\DuplicateIsbn;
use App\Library\Domain\Book\Rating;
use App\Library\Domain\Book\Removal;
use App\Library\Domain\Book\UnknownBook;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Library\Books;
use Tests\Support\Library\Readers;

trait BookRepositoryContract
{
    abstract protected function bookRepository(): BookRepository;

    /**
     * Another repository over the same books, as another request would have.
     */
    abstract protected function anotherSession(): BookRepository;

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

        $repository->remove(Removal::of($book));

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

    #[Test]
    public function a_book_changed_by_another_session_is_not_overwritten(): void
    {
        $mine = $this->bookRepository();
        $theirs = $this->anotherSession();
        $book = Books::aBook();
        $mine->save($book);
        $this->lendThrough($theirs, $book, 'Marie');

        $book->lendTo(Borrower::fromString('Paul'), new DateTimeImmutable('2026-04-02'));

        try {
            $mine->save($book);
            $this->fail('A change made on a book read before another change overwrote it.');
        } catch (BookChangedMeanwhile $bookChangedMeanwhile) {
            $this->assertSame('This book changed in the meantime: read it again before acting on it.', $bookChangedMeanwhile->getMessage());
        }

        $this->assertSame('Marie', $this->anotherSession()->get($book->reader(), $book->id())->loan()?->borrower->value);
    }

    #[Test]
    public function once_read_again_the_book_can_be_changed(): void
    {
        $mine = $this->bookRepository();
        $book = Books::aBook();
        $mine->save($book);
        $this->lendThrough($this->anotherSession(), $book, 'Marie');

        $fresh = $mine->get($book->reader(), $book->id());
        $fresh->markAsReturned();

        $mine->save($fresh);

        $this->assertNull($this->anotherSession()->get($book->reader(), $book->id())->loan());
    }

    #[Test]
    public function every_save_of_a_session_builds_on_its_previous_one(): void
    {
        $mine = $this->bookRepository();
        $book = Books::aBook();
        $mine->save($book);
        $book->lendTo(Borrower::fromString('Marie'), new DateTimeImmutable('2026-04-01'));
        $mine->save($book);

        $theirs = $this->anotherSession();
        $read = $theirs->get($book->reader(), $book->id());
        $read->markAsReturned();

        $theirs->save($read);

        $this->expectException(BookChangedMeanwhile::class);

        $book->markAsReturned();
        $mine->save($book);
    }

    #[Test]
    public function a_book_changed_by_another_session_is_not_removed(): void
    {
        $mine = $this->bookRepository();
        $book = Books::aBook();
        $mine->save($book);
        $this->lendThrough($this->anotherSession(), $book, 'Marie');

        try {
            $mine->remove(Removal::of($book));
            $this->fail('A book lent in the meantime was removed.');
        } catch (BookChangedMeanwhile) {
            $this->assertSame('Marie', $this->anotherSession()->get($book->reader(), $book->id())->loan()?->borrower->value);
        }
    }

    #[Test]
    public function a_book_removed_by_another_session_is_not_saved_back(): void
    {
        $mine = $this->bookRepository();
        $book = Books::aBook();
        $mine->save($book);
        $this->anotherSession()->remove(Removal::of($book));

        $book->lendTo(Borrower::fromString('Paul'), new DateTimeImmutable('2026-04-02'));

        try {
            $mine->save($book);
            $this->fail('A book removed in the meantime came back.');
        } catch (BookChangedMeanwhile) {
            $this->expectException(UnknownBook::class);

            $this->anotherSession()->get($book->reader(), $book->id());
        }
    }

    #[Test]
    public function removing_a_book_another_session_already_removed_changes_nothing(): void
    {
        $mine = $this->bookRepository();
        $book = Books::aBook();
        $mine->save($book);
        $this->anotherSession()->remove(Removal::of($book));

        $mine->remove(Removal::of($book));

        $this->expectException(UnknownBook::class);

        $mine->get($book->reader(), $book->id());
    }

    #[Test]
    public function a_session_that_never_read_a_book_removes_it_as_it_is(): void
    {
        $book = Books::aBook();
        $this->bookRepository()->save($book);
        $this->lendThrough($this->anotherSession(), $book, 'Marie');

        $this->anotherSession()->remove(Removal::of($book));

        $this->expectException(UnknownBook::class);

        $this->anotherSession()->get($book->reader(), $book->id());
    }

    #[Test]
    public function a_book_another_session_saved_first_is_not_saved_over_it(): void
    {
        $book = Books::aBook(isbn: '9780441172719');
        $this->anotherSession()->save($book);

        $this->expectException(BookChangedMeanwhile::class);

        $this->bookRepository()->save($book);
    }

    private function lendThrough(BookRepository $session, Book $book, string $borrower): void
    {
        $read = $session->get($book->reader(), $book->id());
        $read->lendTo(Borrower::fromString($borrower), new DateTimeImmutable('2026-04-01'));

        $session->save($read);
    }
}
