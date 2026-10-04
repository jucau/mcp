<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

interface BookRepository
{
    public function save(Book $book): void;

    /**
     * @throws UnknownBook when the book does not exist, or belongs to another reader
     */
    public function get(ReaderId $reader, BookId $id): Book;

    public function remove(ReaderId $reader, BookId $id): void;
}
