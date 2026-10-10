<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Api;

use App\Library\Application\Book\Query\Shelf;

enum ShelfName: string
{
    case ToRead = 'to-read';
    case Reading = 'reading';
    case Lent = 'lent';
    case Wishlist = 'wishlist';

    public function shelf(): Shelf
    {
        return match ($this) {
            self::ToRead => Shelf::ToRead,
            self::Reading => Shelf::Reading,
            self::Lent => Shelf::Lent,
            self::Wishlist => Shelf::Wishlist,
        };
    }
}
