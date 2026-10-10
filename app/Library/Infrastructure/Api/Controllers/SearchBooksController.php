<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Api\Controllers;

use App\Library\Application\Book\Query\BookQueries;
use App\Library\Application\Book\Query\BookSearch;
use App\Library\Domain\Book\Isbn;
use App\Library\Domain\Book\ReadingStatus;
use App\Library\Infrastructure\Api\Resources\BookResource;
use App\Library\Infrastructure\Reader\CurrentReader;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

final readonly class SearchBooksController
{
    public function __construct(private BookQueries $books)
    {
    }

    public function __invoke(Request $request, CurrentReader $reader): AnonymousResourceCollection
    {
        $request->validate([
            'query' => ['nullable', 'string'],
            'isbn' => ['nullable', 'string'],
            'reading_status' => ['nullable', Rule::enum(ReadingStatus::class)],
            'owned' => ['nullable', 'in:true,false'],
            'lent' => ['nullable', 'in:true,false'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,' . BookSearch::MAX_PER_PAGE],
        ]);

        $isbn = $request->query('isbn');
        $readingStatus = $request->query('reading_status');

        $page = $this->books->search($reader->id(), new BookSearch(
            text: $this->stringOrNull($request, 'query'),
            isbn: is_string($isbn) ? Isbn::fromString($isbn) : null,
            readingStatus: is_string($readingStatus) ? ReadingStatus::from($readingStatus) : null,
            owned: $this->boolOrNull($request, 'owned'),
            lent: $this->boolOrNull($request, 'lent'),
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', 20),
        ));

        return BookResource::collection(new LengthAwarePaginator(
            array_map(static fn ($book): BookResource => new BookResource($book), $page->items),
            $page->total,
            $page->perPage,
            $page->page,
            ['path' => $request->url(), 'query' => $request->query()],
        ));
    }

    private function stringOrNull(Request $request, string $name): ?string
    {
        $value = $request->query($name);

        return is_string($value) ? $value : null;
    }

    private function boolOrNull(Request $request, string $name): ?bool
    {
        return match ($request->query($name)) {
            'true' => true,
            'false' => false,
            default => null,
        };
    }
}
