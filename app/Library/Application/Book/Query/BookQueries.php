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
}
