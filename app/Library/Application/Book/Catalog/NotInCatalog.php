<?php

declare(strict_types=1);

namespace App\Library\Application\Book\Catalog;

use App\Library\Domain\Book\Isbn;
use App\Library\Domain\Refusal;
use RuntimeException;

final class NotInCatalog extends RuntimeException implements Refusal
{
    public static function isbn(Isbn $isbn): self
    {
        return new self(sprintf('The open catalog knows no book with ISBN %s.', $isbn->value));
    }
}
