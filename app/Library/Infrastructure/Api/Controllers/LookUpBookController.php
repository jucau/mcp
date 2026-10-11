<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Api\Controllers;

use App\Library\Application\Book\Catalog\CatalogUnavailable;
use App\Library\Application\Book\Catalog\NotInCatalog;
use App\Library\Application\Book\LookUpBook\LookUpBook;
use App\Library\Application\Book\LookUpBook\LookUpBookHandler;
use App\Library\Domain\Book\InvalidIsbn;
use App\Library\Infrastructure\Api\Resources\CatalogEntryResource;

final readonly class LookUpBookController
{
    public function __construct(private LookUpBookHandler $lookUpBook)
    {
    }

    /**
     * @throws InvalidIsbn
     * @throws NotInCatalog
     * @throws CatalogUnavailable
     */
    public function __invoke(string $isbn): CatalogEntryResource
    {
        return new CatalogEntryResource($this->lookUpBook->handle(new LookUpBook($isbn)));
    }
}
