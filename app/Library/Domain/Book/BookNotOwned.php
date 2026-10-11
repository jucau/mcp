<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use App\Library\Domain\Refusal;
use RuntimeException;

final class BookNotOwned extends RuntimeException implements Refusal
{
    public static function cannotBeLent(Title $title): self
    {
        return new self(sprintf('"%s" is on the wishlist, not owned: it cannot be lent.', $title->value));
    }
}
