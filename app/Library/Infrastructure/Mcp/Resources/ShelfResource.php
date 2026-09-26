<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp\Resources;

use App\Library\Application\Book\Query\BookQueries;
use App\Library\Infrastructure\Mcp\BookPresenter;
use App\Library\Infrastructure\Mcp\Shelf;
use Laravel\Mcp\Request;

abstract class ShelfResource extends LibraryResource
{
    public function __construct(
        private readonly BookQueries $books,
        private readonly BookPresenter $presenter,
    ) {
    }

    final protected function read(Request $request): array
    {
        return ['books' => array_map($this->presenter->present(...), $this->books->all($this->shelf()->search()))];
    }

    abstract protected function shelf(): Shelf;
}
