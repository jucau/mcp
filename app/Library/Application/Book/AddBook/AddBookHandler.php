<?php

declare(strict_types=1);

namespace App\Library\Application\Book\AddBook;

use App\Library\Domain\Book\Author;
use App\Library\Domain\Book\Book;
use App\Library\Domain\Book\BookId;
use App\Library\Domain\Book\BookRepository;
use App\Library\Domain\Book\Isbn;
use App\Library\Domain\Book\ReaderId;
use App\Library\Domain\Book\Title;

final readonly class AddBookHandler
{
    public function __construct(private BookRepository $books)
    {
    }

    public function handle(AddBook $command): BookId
    {
        $book = Book::add(
            BookId::generate(),
            ReaderId::fromString($command->readerId),
            Title::fromString($command->title),
            Author::fromString($command->author),
            $command->isbn === null ? null : Isbn::fromString($command->isbn),
            $command->owned,
        );

        $this->books->save($book);

        return $book->id();
    }
}
