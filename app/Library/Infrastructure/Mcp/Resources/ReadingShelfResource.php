<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp\Resources;

use App\Library\Application\Book\Query\BookSearch;
use App\Library\Domain\Book\ReadingStatus;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;

#[Uri('library://shelves/reading')]
#[Name('shelf_reading')]
#[Title('Reading now')]
#[Description('The books the user is reading now, with the day they started, sorted by title.')]
final class ReadingShelfResource extends ShelfResource
{
    protected function shelf(int $page, int $perPage): BookSearch
    {
        return new BookSearch(readingStatus: ReadingStatus::Reading, page: $page, perPage: $perPage);
    }
}
