<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

interface BookRepository
{
    public function save(Book $book): void;

    public function get(BookId $id): Book;

    public function remove(BookId $id): void;
}
