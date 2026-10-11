<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Catalog;

use App\Library\Application\Book\Catalog\BookCatalog;
use App\Library\Application\Book\Catalog\CatalogEntry;
use App\Library\Application\Book\Catalog\CatalogUnavailable;
use App\Library\Domain\Book\Isbn;
use App\Library\Domain\Book\Text;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;

final readonly class OpenLibraryBookCatalog implements BookCatalog
{
    private const int TIMEOUT_IN_SECONDS = 5;

    // Open Library asks its regular callers to name themselves, and throttles anonymous ones harder.
    private const string USER_AGENT = 'Marque-page (a pedagogical MCP server)';

    public function __construct(private Factory $http, private string $baseUrl)
    {
    }

    public function find(Isbn $isbn): ?CatalogEntry
    {
        $key = 'ISBN:' . $isbn->value;

        try {
            $response = $this->http
                ->baseUrl($this->baseUrl)
                ->timeout(self::TIMEOUT_IN_SECONDS)
                ->withUserAgent(self::USER_AGENT)
                ->acceptJson()
                ->get('/api/books', ['bibkeys' => $key, 'format' => 'json', 'jscmd' => 'data']);
        } catch (ConnectionException) {
            throw CatalogUnavailable::now();
        }

        $books = $response->json();

        if (! $response->successful() || ! is_array($books)) {
            throw CatalogUnavailable::now();
        }

        $book = $books[$key] ?? null;
        $title = is_array($book) ? $this->text($book['title'] ?? null) : null;

        if (! is_array($book) || $title === null) {
            return null;
        }

        return new CatalogEntry(
            $isbn->value,
            $title,
            $this->authors($book['authors'] ?? null),
            $this->year($book['publish_date'] ?? null),
            $this->pages($book['number_of_pages'] ?? null),
        );
    }

    /**
     * One line, no longer than what the domain accepts: an entry can always be handed to add_book as it is.
     */
    private function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $line = mb_substr(trim((string) preg_replace('/\s+/u', ' ', $value)), 0, Text::MAX_LENGTH);

        return $line === '' ? null : $line;
    }

    private function authors(mixed $authors): ?string
    {
        if (! is_array($authors)) {
            return null;
        }

        $names = [];

        foreach ($authors as $author) {
            $name = is_array($author) ? $this->text($author['name'] ?? null) : null;

            if ($name !== null) {
                $names[] = $name;
            }
        }

        return $this->text(implode(' & ', $names));
    }

    private function year(mixed $publishDate): ?int
    {
        return is_string($publishDate) && preg_match('/\b(\d{4})\b/', $publishDate, $found) === 1 ? (int) $found[1] : null;
    }

    private function pages(mixed $pages): ?int
    {
        return is_int($pages) && $pages > 0 ? $pages : null;
    }
}
