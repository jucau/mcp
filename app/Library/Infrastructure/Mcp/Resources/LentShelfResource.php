<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp\Resources;

use App\Library\Application\Book\Query\BookSearch;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;

#[Uri('library://shelves/lent')]
#[Name('shelf_lent')]
#[Title('Lent')]
#[Description('The books the user lent, with whom and since when, sorted by title.')]
final class LentShelfResource extends ShelfResource
{
    protected function shelf(int $page, int $perPage): BookSearch
    {
        return new BookSearch(lent: true, page: $page, perPage: $perPage);
    }
}
