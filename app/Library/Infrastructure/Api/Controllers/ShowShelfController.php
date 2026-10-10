<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Api\Controllers;

use App\Library\Application\Book\Query\BookQueries;
use App\Library\Application\Book\Query\BookView;
use App\Library\Application\Book\Query\Shelf;
use App\Library\Infrastructure\Api\Resources\BookResource;
use App\Library\Infrastructure\Reader\CurrentReader;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class ShowShelfController
{
    public const array SHELVES = [
        'to-read' => Shelf::ToRead,
        'reading' => Shelf::Reading,
        'lent' => Shelf::Lent,
        'wishlist' => Shelf::Wishlist,
    ];

    public function __construct(private BookQueries $books)
    {
    }

    public function __invoke(string $shelf, CurrentReader $reader): AnonymousResourceCollection
    {
        return BookResource::collection(array_map(
            static fn (BookView $book): BookResource => new BookResource($book),
            $this->books->all($reader->id(), self::SHELVES[$shelf]->search()),
        ));
    }
}
