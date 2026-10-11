<?php

declare(strict_types=1);

namespace App\Library\Application\Book\LookUpBook;

use App\Library\Application\Book\Catalog\BookCatalog;
use App\Library\Application\Book\Catalog\CatalogEntry;
use App\Library\Application\Book\Catalog\CatalogUnavailable;
use App\Library\Application\Book\Catalog\NotInCatalog;
use App\Library\Domain\Book\InvalidIsbn;
use App\Library\Domain\Book\Isbn;

final readonly class LookUpBookHandler
{
    public function __construct(private BookCatalog $catalog)
    {
    }

    /**
     * @throws InvalidIsbn
     * @throws NotInCatalog
     * @throws CatalogUnavailable
     */
    public function handle(LookUpBook $query): CatalogEntry
    {
        $isbn = Isbn::fromString($query->isbn);

        return $this->catalog->find($isbn) ?? throw NotInCatalog::isbn($isbn);
    }
}
