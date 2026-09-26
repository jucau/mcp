<?php

declare(strict_types=1);

namespace App\Library\Application\Book\Query;

use App\Library\Application\Page;

interface BookQueries
{
    public function find(string $bookId): ?BookView;

    /**
     * @return Page<BookView>
     */
    public function search(BookSearch $criteria): Page;

    /**
     * Every matching book, sorted by title: the page of the criteria is ignored.
     *
     * @return list<BookView>
     */
    public function all(BookSearch $criteria): array;
}
