<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp\Resources;

use App\Library\Application\Book\Query\BookSearch;
use App\Library\Domain\Book\ReadingStatus;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;

#[Uri('library://shelves/to-read')]
#[Name('shelf_to_read')]
#[Title('To read')]
#[Description('The books the user owns, has at home and has not started yet, sorted by title.')]
final class ToReadShelfResource extends ShelfResource
{
    protected function shelf(int $page, int $perPage): BookSearch
    {
        return new BookSearch(readingStatus: ReadingStatus::ToRead, owned: true, lent: false, page: $page, perPage: $perPage);
    }
}
