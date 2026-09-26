<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp\Prompts;

use App\Library\Application\Book\Query\BookView;
use App\Library\Infrastructure\Mcp\BookPresenter;

final readonly class EmbeddedBooks
{
    public function __construct(private BookPresenter $presenter)
    {
    }

    /**
     * @param list<BookView> $books
     */
    public function json(array $books): string
    {
        return json_encode(array_map($this->presenter->present(...), $books), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
