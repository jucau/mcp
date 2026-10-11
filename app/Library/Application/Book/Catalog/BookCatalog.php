<?php

declare(strict_types=1);

namespace App\Library\Application\Book\Catalog;

use App\Library\Domain\Book\Isbn;

interface BookCatalog
{
    /**
     * @throws CatalogUnavailable
     */
    public function find(Isbn $isbn): ?CatalogEntry;
}
