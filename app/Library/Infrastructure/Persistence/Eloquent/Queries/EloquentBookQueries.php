<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Persistence\Eloquent\Queries;

use App\Library\Application\Book\Query\BookQueries;
use App\Library\Application\Book\Query\BookSearch;
use App\Library\Application\Book\Query\BookView;
use App\Library\Application\Page;
use App\Library\Domain\Book\BookId;
use App\Library\Domain\Book\Isbn;
use App\Library\Domain\Book\ReadingStatus;
use App\Library\Infrastructure\Persistence\Eloquent\Models\BookRecord;
use Illuminate\Database\Eloquent\Builder;

final class EloquentBookQueries implements BookQueries
{
    public function find(string $bookId): ?BookView
    {
        $record = BookRecord::query()->where('uuid', BookId::fromString($bookId)->value)->first();

        return $record instanceof BookRecord ? $this->view($record) : null;
    }

    /**
     * @return Page<BookView>
     */
    public function search(BookSearch $criteria): Page
    {
        $query = BookRecord::query();

        $text = trim($criteria->text ?? '');

        if ($text !== '') {
            $pattern = $this->containing($text);
            $query->where(fn (Builder $matching) => $matching->where('title', 'ilike', $pattern)->orWhere('author', 'ilike', $pattern));
        }

        if ($criteria->isbn instanceof Isbn) {
            $query->where('isbn', $criteria->isbn->value);
        }

        if ($criteria->readingStatus instanceof ReadingStatus) {
            $query->where('reading_status', $criteria->readingStatus->value);
        }

        if ($criteria->owned !== null) {
            $query->where('owned', $criteria->owned);
        }

        if ($criteria->lent === true) {
            $query->whereNotNull('loan_borrower');
        }

        if ($criteria->lent === false) {
            $query->whereNull('loan_borrower');
        }

        $paginator = $query->orderBy('title')->orderBy('id')->paginate(perPage: $criteria->perPage, page: $criteria->page);

        $items = [];

        foreach ($paginator->items() as $record) {
            $items[] = $this->view($record);
        }

        return new Page($items, $paginator->currentPage(), $paginator->perPage(), $paginator->total());
    }

    private function containing(string $text): string
    {
        return '%' . addcslashes($text, '%_\\') . '%';
    }

    private function view(BookRecord $record): BookView
    {
        return new BookView(
            $record->uuid,
            $record->title,
            $record->author,
            $record->isbn,
            $record->owned,
            $record->reading_status,
            $record->started_on?->format('Y-m-d'),
            $record->finished_on?->format('Y-m-d'),
            $record->rating,
            $record->loan_borrower,
            $record->lent_on?->format('Y-m-d'),
        );
    }
}
