<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Persistence\Eloquent\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $title
 * @property string $author
 * @property string|null $isbn
 * @property bool $owned
 * @property string $reading_status
 * @property CarbonImmutable|null $started_on
 * @property CarbonImmutable|null $finished_on
 * @property int|null $rating
 * @property string|null $loan_borrower
 * @property CarbonImmutable|null $lent_on
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @mixin \Eloquent
 */
final class BookRecord extends Model
{
    protected $table = 'books';

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'owned' => 'boolean',
            'started_on' => 'immutable_date',
            'finished_on' => 'immutable_date',
            'lent_on' => 'immutable_date',
            'rating' => 'integer',
        ];
    }
}
