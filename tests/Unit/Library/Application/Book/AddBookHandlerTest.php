<?php

declare(strict_types=1);

namespace Tests\Unit\Library\Application\Book;

use App\Library\Application\Book\AddBook\AddBook;
use App\Library\Application\Book\AddBook\AddBookHandler;
use App\Library\Domain\Book\BookId;
use App\Library\Domain\Book\DuplicateIsbn;
use App\Library\Domain\Book\InvalidIsbn;
use App\Library\Domain\Book\InvalidText;
use App\Library\Domain\Book\ReadingStatus;
use App\Library\Domain\Book\UnknownBook;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Library\Books;
use Tests\Support\Library\Readers;
use Tests\Unit\Library\Application\UseCaseTestCase;

final class AddBookHandlerTest extends UseCaseTestCase
{
    #[Test]
    public function an_owned_book_joins_the_library_to_be_read(): void
    {
        $id = $this->addBook(new AddBook(Readers::ME, " Dune \n", ' Frank Herbert ', '0-441-17271-7'));

        $book = $this->book($id);
        $this->assertSame('Dune', $book->title()->value);
        $this->assertSame('Frank Herbert', $book->author()->value);
        $this->assertSame('9780441172719', $book->isbn()?->value);
        $this->assertTrue($book->isOwned());
        $this->assertSame(ReadingStatus::ToRead, $book->readingStatus());
        $this->assertNull($book->rating());
        $this->assertNull($book->loan());
    }

    #[Test]
    public function a_book_joins_the_wishlist(): void
    {
        $id = $this->addBook(new AddBook(Readers::ME, 'Hypérion', 'Dan Simmons', owned: false));

        $this->assertFalse($this->book($id)->isOwned());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validIsbns(): iterable
    {
        yield 'ISBN-13' => ['9782070360024', '9782070360024'];
        yield 'ISBN-13 with hyphens' => ['978-0-306-40615-7', '9780306406157'];
        yield 'ISBN-13 with the 979 prefix' => ['979-10-90636-07-1', '9791090636071'];
        yield 'ISBN-10, converted to ISBN-13' => ['0-306-40615-2', '9780306406157'];
        yield 'ISBN-10 ending with X' => ['0-8044-2957-X', '9780804429573'];
        yield 'ISBN-10 whose check digit is 0' => ['0-306-40602-0', '9780306406027'];
        yield 'ISBN-10 ending with a lowercase x' => ['080442957x', '9780804429573'];
        yield 'ISBN with spaces' => [' 2 07 036002 4 ', '9782070360024'];
        yield 'ISBN-13 whose check digit is 0' => ['978-0-301-00006-0', '9780301000060'];
    }

    #[Test]
    #[DataProvider('validIsbns')]
    public function the_isbn_is_kept_as_isbn_13(string $typed, string $kept): void
    {
        $id = $this->addBook(new AddBook(Readers::ME, 'Dune', 'Frank Herbert', $typed));

        $this->assertSame($kept, $this->book($id)->isbn()?->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIsbns(): iterable
    {
        yield 'wrong ISBN-10 check digit' => ['0306406153'];
        yield 'wrong ISBN-13 check digit' => ['9780306406158'];
        yield 'ISBN-13 without the 978/979 prefix' => ['1234567890128'];
        yield 'X anywhere but last' => ['08044X9573'];
        yield 'too short' => ['978030640615'];
        yield 'ISBN-10 followed by an extra digit' => ['0-306-40615-2 1'];
        yield 'ISBN-13 followed by an extra digit' => ['978-0-306-40615-7 1'];
        yield 'ISBN-13 preceded by an extra digit' => ['7-978-0-306-40615-7'];
        yield 'letters' => ['not-an-isbn'];
        yield 'empty' => [''];
    }

    #[Test]
    #[DataProvider('invalidIsbns')]
    public function an_invalid_isbn_is_refused(string $typed): void
    {
        $this->expectException(InvalidIsbn::class);

        $this->addBook(new AddBook(Readers::ME, 'Dune', 'Frank Herbert', $typed));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidTexts(): iterable
    {
        yield 'blank title' => [" \t ", 'Frank Herbert'];
        yield 'blank author' => ['Dune', ''];
        yield 'title over 255 characters' => [str_repeat('é', 256), 'Frank Herbert'];
        yield 'author over 255 characters' => ['Dune', str_repeat('é', 256)];
    }

    #[Test]
    #[DataProvider('invalidTexts')]
    public function a_blank_or_too_long_title_or_author_is_refused(string $title, string $author): void
    {
        $this->expectException(InvalidText::class);

        $this->addBook(new AddBook(Readers::ME, $title, $author));
    }

    #[Test]
    public function a_title_of_255_multibyte_characters_is_accepted(): void
    {
        $id = $this->addBook(new AddBook(Readers::ME, str_repeat('é', 255), 'Frank Herbert'));

        $this->assertSame(255, mb_strlen($this->book($id)->title()->value));
    }

    #[Test]
    public function a_book_whose_isbn_is_already_in_the_library_is_refused(): void
    {
        $this->given(Books::aBook(isbn: '9780441172719'));

        $this->expectException(DuplicateIsbn::class);

        $this->addBook(new AddBook(Readers::ME, 'Dune', 'Frank Herbert', '0441172717'));
    }

    #[Test]
    public function the_book_joins_the_library_of_its_reader_only(): void
    {
        $id = $this->addBook(new AddBook(Readers::ME, 'Dune', 'Frank Herbert'));

        $this->assertTrue($this->book($id)->reader()->equals(Readers::me()));
        $this->expectException(UnknownBook::class);

        $this->books->get(Readers::someoneElse(), BookId::fromString($id));
    }

    #[Test]
    public function another_reader_may_add_a_book_with_the_same_isbn(): void
    {
        $this->given(Books::aBook(isbn: '9780441172719', reader: Readers::someoneElse()));

        $id = $this->addBook(new AddBook(Readers::ME, 'Dune', 'Frank Herbert', '0-441-17271-7'));

        $this->assertSame('9780441172719', $this->book($id)->isbn()?->value);
    }

    private function addBook(AddBook $command): string
    {
        return (new AddBookHandler($this->books))->handle($command)->value;
    }
}
