<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp;

use App\Library\Application\Book\Query\BookView;
use App\Library\Domain\Book\Rating;
use App\Library\Domain\Book\ReadingStatus;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

final class BookPresenter
{
    /**
     * @return array<string, Type>
     */
    public static function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->format('uuid')->required(),
            'title' => $schema->string()->required(),
            'author' => $schema->string()->required(),
            'isbn' => $schema->string()->nullable()->required()->description('The ISBN-13, null when unknown.'),
            'owned' => $schema->boolean()->required()->description('false for a book on the wishlist.'),
            'reading_status' => $schema->string()->enum(array_column(ReadingStatus::cases(), 'value'))->required(),
            'started_on' => $schema->string()->format('date')->nullable()->required()->description('The day the reading started.'),
            'finished_on' => $schema->string()->format('date')->nullable()->required()->description('The day the book was finished or abandoned.'),
            'rating' => $schema->integer()->min(Rating::MIN)->max(Rating::MAX)->nullable()->required(),
            'lent_to' => $schema->string()->nullable()->required()->description('Who borrowed it, null when the book is at home.'),
            'lent_on' => $schema->string()->format('date')->nullable()->required(),
        ];
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    public static function present(BookView $book): array
    {
        return [
            'id' => $book->id,
            'title' => $book->title,
            'author' => $book->author,
            'isbn' => $book->isbn,
            'owned' => $book->owned,
            'reading_status' => $book->readingStatus,
            'started_on' => $book->startedOn,
            'finished_on' => $book->finishedOn,
            'rating' => $book->rating,
            'lent_to' => $book->borrower,
            'lent_on' => $book->lentOn,
        ];
    }
}
