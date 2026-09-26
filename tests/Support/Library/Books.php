<?php

declare(strict_types=1);

namespace Tests\Support\Library;

use App\Library\Domain\Book\Author;
use App\Library\Domain\Book\Book;
use App\Library\Domain\Book\BookId;
use App\Library\Domain\Book\Isbn;
use App\Library\Domain\Book\Title;

final class Books
{
    public static function aBook(
        string $title = 'Dune',
        string $author = 'Frank Herbert',
        ?string $isbn = null,
        bool $owned = true,
    ): Book {
        return Book::add(
            BookId::generate(),
            Title::fromString($title),
            Author::fromString($author),
            $isbn === null ? null : Isbn::fromString($isbn),
            $owned,
        );
    }
}
