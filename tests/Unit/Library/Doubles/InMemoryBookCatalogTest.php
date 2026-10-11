<?php

declare(strict_types=1);

namespace Tests\Unit\Library\Doubles;

use App\Library\Application\Book\Catalog\BookCatalog;
use App\Library\Application\Book\Catalog\CatalogEntry;
use PHPUnit\Framework\TestCase;
use Tests\Support\Library\Contract\BookCatalogContract;
use Tests\Support\Library\InMemoryBookCatalog;

final class InMemoryBookCatalogTest extends TestCase
{
    use BookCatalogContract;

    protected function catalogKnowing(CatalogEntry $entry): BookCatalog
    {
        return new InMemoryBookCatalog()->knows($entry);
    }

    protected function catalogDown(): BookCatalog
    {
        return new InMemoryBookCatalog()->goDown();
    }
}
