<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Api;

use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Library\Books;

final class AcquireBookTest extends ApiTestCase
{
    #[Test]
    public function a_book_wished_for_becomes_owned(): void
    {
        $book = $this->given(Books::aBook(owned: false));

        $this->postJson('/api/books/' . $book->id()->value . '/acquire')
            ->assertOk()
            ->assertJsonPath('data.owned', true);
    }

    #[Test]
    public function a_book_already_owned_stays_as_it_is(): void
    {
        $book = $this->given(Books::aBook());

        $this->postJson('/api/books/' . $book->id()->value . '/acquire')
            ->assertOk()
            ->assertJsonPath('data.owned', true);
    }
}
