<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Api\Resources;

use App\Library\Application\Book\Query\BookView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class BookResource extends JsonResource
{
    public function __construct(private readonly BookView $book)
    {
        parent::__construct($book);
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->book->id,
            'title' => $this->book->title,
            'author' => $this->book->author,
            'isbn' => $this->book->isbn,
            'owned' => $this->book->owned,
            'reading_status' => $this->book->readingStatus,
            'started_on' => $this->book->startedOn,
            'finished_on' => $this->book->finishedOn,
            'rating' => $this->book->rating,
            'lent_to' => $this->book->borrower,
            'lent_on' => $this->book->lentOn,
        ];
    }
}
