<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Persistence\Eloquent\Queries;

use App\Library\Application\Book\Query\BookQueries;
use App\Library\Application\Book\Query\BookSearch;
use App\Library\Application\Book\Query\BookView;
use App\Library\Application\Page;
use App\Library\Domain\Book\BookId;
use App\Library\Domain\Book\Isbn;
use App\Library\Domain\Book\ReaderId;
use App\Library\Domain\Book\ReadingStatus;
use App\Library\Infrastructure\Persistence\Eloquent\Models\BookRecord;
use Illuminate\Database\Eloquent\Builder;

final class EloquentBookQueries implements BookQueries
{
    public function find(ReaderId $reader, string $bookId): ?BookView
    {
        $record = BookRecord::query()
            ->where('reader_id', $reader->value)
            ->where('uuid', BookId::fromString($bookId)->value)
            ->first();

        return $record instanceof BookRecord ? $this->view($record) : null;
    }

    /**
     * @return Page<BookView>
     */
    public function search(ReaderId $reader, BookSearch $criteria): Page
    {
        $paginator = $this->matching($reader, $criteria)->paginate(perPage: $criteria->perPage, page: $criteria->page);

        $items = [];

        foreach ($paginator->items() as $record) {
            $items[] = $this->view($record);
        }

        return new Page($items, $paginator->currentPage(), $paginator->perPage(), $paginator->total());
    }

    public function all(ReaderId $reader, BookSearch $criteria): array
    {
        return array_values(array_map($this->view(...), $this->matching($reader, $criteria)->get()->all()));
    }

    /**
     * @return Builder<BookRecord>
     */
    private function matching(ReaderId $reader, BookSearch $criteria): Builder
    {
        $query = BookRecord::query()->where('reader_id', $reader->value);

        foreach ($this->words($criteria->text ?? '') as $word) {
            $pattern = $this->containing($word);
            $query->where(fn (Builder $matching) => $matching
                ->whereRaw('unaccent(title) ilike unaccent(?)', [$pattern])
                ->orWhereRaw('unaccent(author) ilike unaccent(?)', [$pattern]));
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

        if ($criteria->finishedIn !== null) {
            $query->whereBetween('finished_on', [sprintf('%04d-01-01', $criteria->finishedIn), sprintf('%04d-12-31', $criteria->finishedIn)]);
        }

        return $query->orderBy('title')->orderBy('id');
    }

    /**
     * @return list<string>
     */
    private function words(string $text): array
    {
        return preg_split('/\s+/u', $text, flags: PREG_SPLIT_NO_EMPTY) ?: [];
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
