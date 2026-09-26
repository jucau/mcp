<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp;

use App\Library\Application\Book\Query\BookView;

final class BookPresenter
{
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
