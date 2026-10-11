<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

interface BookRepository
{
    /**
     * @throws DuplicateIsbn when the reader already has a book with this ISBN
     * @throws BookChangedMeanwhile when the book was changed or removed since this repository last read or saved it
     */
    public function save(Book $book): void;

    /**
     * @throws UnknownBook when the book does not exist, or belongs to another reader
     */
    public function get(ReaderId $reader, BookId $id): Book;

    /**
     * @throws BookChangedMeanwhile when the book was changed since this repository last read or saved it
     */
    public function remove(Removal $removal): void;
}
