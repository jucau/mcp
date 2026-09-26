<?php

declare(strict_types=1);

namespace Tests\Support\Library;

final class BookSchema
{
    public const array BOOK = [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'format' => 'uuid'],
            'title' => ['type' => 'string'],
            'author' => ['type' => 'string'],
            'isbn' => ['type' => ['string', 'null'], 'description' => 'The ISBN-13, null when unknown.'],
            'owned' => ['type' => 'boolean', 'description' => 'false for a book on the wishlist.'],
            'reading_status' => ['type' => 'string', 'enum' => ['to_read', 'reading', 'read', 'abandoned']],
            'started_on' => ['type' => ['string', 'null'], 'format' => 'date', 'description' => 'The day the reading started.'],
            'finished_on' => ['type' => ['string', 'null'], 'format' => 'date', 'description' => 'The day the book was finished or abandoned.'],
            'rating' => ['type' => ['integer', 'null'], 'minimum' => 1, 'maximum' => 5],
            'lent_to' => ['type' => ['string', 'null'], 'description' => 'Who borrowed it, null when the book is at home.'],
            'lent_on' => ['type' => ['string', 'null'], 'format' => 'date'],
        ],
        'required' => ['id', 'title', 'author', 'isbn', 'owned', 'reading_status', 'started_on', 'finished_on', 'rating', 'lent_to', 'lent_on'],
    ];
}
