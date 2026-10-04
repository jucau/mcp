<?php

declare(strict_types=1);

namespace App\Library\Application\Book\Query;

use App\Library\Application\Page;
use App\Library\Domain\Book\ReaderId;

interface BookQueries
{
    public function find(ReaderId $reader, string $bookId): ?BookView;

    /**
     * @return Page<BookView>
     */
    public function search(ReaderId $reader, BookSearch $criteria): Page;

    /**
     * Every matching book, sorted by title: the page of the criteria is ignored.
     *
     * @return list<BookView>
     */
    public function all(ReaderId $reader, BookSearch $criteria): array;
}
