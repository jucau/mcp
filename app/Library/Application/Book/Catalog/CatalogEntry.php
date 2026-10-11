<?php

declare(strict_types=1);

namespace App\Library\Application\Book\Catalog;

final readonly class CatalogEntry
{
    public function __construct(
        public string $isbn,
        public string $title,
        public ?string $author = null,
        public ?int $publishedIn = null,
        public ?int $pages = null,
    ) {
    }
}
