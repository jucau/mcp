<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp\Resources;

use App\Library\Application\Book\Query\BookQueries;
use App\Library\Infrastructure\Mcp\BookPresenter;
use App\Library\Infrastructure\Mcp\Shelf;
use App\Library\Infrastructure\Reader\CurrentReader;
use Laravel\Mcp\Request;

abstract class ShelfResource extends LibraryResource
{
    public function __construct(
        private readonly BookQueries $books,
        private readonly BookPresenter $presenter,
        private readonly CurrentReader $reader,
    ) {
    }

    final protected function read(Request $request): array
    {
        return ['books' => array_map($this->presenter->present(...), $this->books->all($this->reader->id(), $this->shelf()->search()))];
    }

    abstract protected function shelf(): Shelf;
}
