<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Catalog;

use App\Library\Application\Book\Catalog\BookCatalog;
use App\Library\Application\Book\Catalog\CatalogEntry;
use App\Library\Application\Book\Catalog\CatalogUnavailable;
use App\Library\Domain\Book\Isbn;

/**
 * A catalog for working without a network. Every entry is computed from the ISBN asked, so the same ISBN always
 * gets the same answer, and is plainly fake: none can be mistaken for a real book once added to a library.
 */
final readonly class FakeBookCatalog implements BookCatalog
{
    public const string LAST_DIGIT_OF_AN_UNKNOWN_ISBN = '0';

    public const string LAST_DIGIT_THAT_BRINGS_THE_CATALOG_DOWN = '9';

    public function find(Isbn $isbn): ?CatalogEntry
    {
        $lastDigit = substr($isbn->value, -1);

        if ($lastDigit === self::LAST_DIGIT_THAT_BRINGS_THE_CATALOG_DOWN) {
            throw CatalogUnavailable::now();
        }

        if ($lastDigit === self::LAST_DIGIT_OF_AN_UNKNOWN_ISBN) {
            return null;
        }

        $seed = (int) substr($isbn->value, -4);

        return new CatalogEntry(
            $isbn->value,
            'Fake book ' . $isbn->value,
            'Fake Author',
            1950 + $seed % 75,
            100 + $seed % 400,
        );
    }
}
