<?php

declare(strict_types=1);

namespace Tests\Support\Library;

use App\Library\Application\Book\Catalog\BookCatalog;
use App\Library\Application\Book\Catalog\CatalogEntry;
use App\Library\Application\Book\Catalog\CatalogUnavailable;
use App\Library\Domain\Book\Isbn;

final class InMemoryBookCatalog implements BookCatalog
{
    /** @var array<string, CatalogEntry> */
    private array $entries = [];

    /** @var list<string> */
    private array $asked = [];

    private bool $down = false;

    public function knows(CatalogEntry ...$entries): self
    {
        foreach ($entries as $entry) {
            $this->entries[$entry->isbn] = $entry;
        }

        return $this;
    }

    public function goDown(): self
    {
        $this->down = true;

        return $this;
    }

    public function find(Isbn $isbn): ?CatalogEntry
    {
        $this->asked[] = $isbn->value;

        if ($this->down) {
            throw CatalogUnavailable::now();
        }

        return $this->entries[$isbn->value] ?? null;
    }

    /**
     * @return list<string>
     */
    public function asked(): array
    {
        return $this->asked;
    }
}
