<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Repository;

use App\Library\Domain\Book\BookRepository;
use App\Library\Infrastructure\Persistence\Eloquent\Repositories\EloquentBookRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\Library\Contract\BookRepositoryContract;
use Tests\TestCase;

final class EloquentBookRepositoryTest extends TestCase
{
    use BookRepositoryContract;
    use DatabaseTransactions;

    private ?EloquentBookRepository $books = null;

    protected function bookRepository(): BookRepository
    {
        return $this->books ??= new EloquentBookRepository();
    }

    protected function anotherSession(): BookRepository
    {
        return new EloquentBookRepository();
    }
}
