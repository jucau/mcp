<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp;

use App\Library\Application\Book\Query\BookSearch;
use App\Library\Domain\Book\ReadingStatus;

enum Shelf
{
    case ToRead;
    case Reading;
    case Lent;
    case Wishlist;

    public function search(): BookSearch
    {
        return match ($this) {
            self::ToRead => new BookSearch(readingStatus: ReadingStatus::ToRead, owned: true, lent: false),
            self::Reading => new BookSearch(readingStatus: ReadingStatus::Reading),
            self::Lent => new BookSearch(lent: true),
            self::Wishlist => new BookSearch(owned: false),
        };
    }
}
