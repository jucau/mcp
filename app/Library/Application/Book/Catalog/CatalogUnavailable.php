<?php

declare(strict_types=1);

namespace App\Library\Application\Book\Catalog;

use App\Library\Domain\Refusal;
use RuntimeException;

final class CatalogUnavailable extends RuntimeException implements Refusal
{
    public static function now(): self
    {
        return new self('The open catalog cannot be reached right now.');
    }
}
