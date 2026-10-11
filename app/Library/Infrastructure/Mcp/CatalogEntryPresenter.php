<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp;

use App\Library\Application\Book\Catalog\CatalogEntry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

final readonly class CatalogEntryPresenter
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'isbn' => $schema->string()->required()->description('The ISBN-13 that was looked up.'),
            'title' => $schema->string()->required(),
            'author' => $schema->string()->nullable()->required()
                ->description('Every author in one text, as add_book expects it. null when the catalog names none: ask the user.'),
            'published_in' => $schema->integer()->nullable()->required()->description('The year this edition was published.'),
            'pages' => $schema->integer()->nullable()->required(),
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    public function present(CatalogEntry $entry): array
    {
        return [
            'isbn' => $entry->isbn,
            'title' => $entry->title,
            'author' => $entry->author,
            'published_in' => $entry->publishedIn,
            'pages' => $entry->pages,
        ];
    }
}
