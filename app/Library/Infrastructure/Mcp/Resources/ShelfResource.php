<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp\Resources;

use App\Library\Application\Book\Query\BookQueries;
use App\Library\Application\Book\Query\BookSearch;
use App\Library\Infrastructure\Mcp\BookPresenter;
use Laravel\Mcp\Request;

abstract class ShelfResource extends LibraryResource
{
    public function __construct(private readonly BookQueries $books)
    {
    }

    final protected function read(Request $request): array
    {
        $books = [];
        $page = 1;

        do {
            $found = $this->books->search($this->shelf($page, BookSearch::MAX_PER_PAGE));

            foreach ($found->items as $book) {
                $books[] = BookPresenter::present($book);
            }

            ++$page;
        } while (count($books) < $found->total);

        return ['books' => $books];
    }

    abstract protected function shelf(int $page, int $perPage): BookSearch;
}
