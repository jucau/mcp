<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Api\Resources;

use App\Library\Application\Book\Catalog\CatalogEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CatalogEntryResource extends JsonResource
{
    public function __construct(private readonly CatalogEntry $entry)
    {
        parent::__construct($entry);
    }

    /**
     * @return array<string, int|string|null>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'isbn' => $this->entry->isbn,
            'title' => $this->entry->title,
            'author' => $this->entry->author,
            'published_in' => $this->entry->publishedIn,
            'pages' => $this->entry->pages,
        ];
    }
}
