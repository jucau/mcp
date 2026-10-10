<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Api\Controllers;

use App\Library\Application\Book\AddBook\AddBook;
use App\Library\Application\Book\AddBook\AddBookHandler;
use App\Library\Application\Book\Query\BookQueries;
use App\Library\Application\Book\Query\BookView;
use App\Library\Domain\Book\DuplicateIsbn;
use App\Library\Domain\Book\InvalidIsbn;
use App\Library\Domain\Book\InvalidText;
use App\Library\Infrastructure\Api\Resources\BookResource;
use App\Library\Infrastructure\Reader\CurrentReader;
use App\Library\Infrastructure\ValidatedData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

final readonly class AddBookController
{
    public function __construct(private AddBookHandler $addBook, private BookQueries $books)
    {
    }

    /**
     * @throws InvalidText
     * @throws InvalidIsbn
     * @throws DuplicateIsbn
     */
    public function __invoke(Request $request, CurrentReader $reader): JsonResponse
    {
        $body = ValidatedData::of($request->validate([
            'title' => ['required', 'string'],
            'author' => ['required', 'string'],
            'isbn' => ['nullable', 'string'],
            'owned' => ['nullable', 'boolean:strict'],
        ]));

        $id = $this->addBook->handle(new AddBook(
            $reader->id()->value,
            $body->string('title'),
            $body->string('author'),
            $body->stringOrNull('isbn'),
            $body->boolOrNull('owned') ?? true,
        ));

        $book = $this->books->find($reader->id(), $id->value);
        Assert::isInstanceOf($book, BookView::class);

        return new BookResource($book)->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', url('api/books/' . $id->value));
    }
}
