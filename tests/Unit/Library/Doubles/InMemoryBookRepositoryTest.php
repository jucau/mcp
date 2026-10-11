<?php

declare(strict_types=1);

namespace Tests\Unit\Library\Doubles;

use App\Library\Domain\Book\BookRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\Library\Contract\BookRepositoryContract;
use Tests\Support\Library\InMemoryBookRepository;

final class InMemoryBookRepositoryTest extends TestCase
{
    use BookRepositoryContract;

    private ?InMemoryBookRepository $books = null;

    protected function bookRepository(): BookRepository
    {
        return $this->books ??= new InMemoryBookRepository();
    }

    protected function anotherSession(): BookRepository
    {
        return ($this->books ??= new InMemoryBookRepository())->anotherSession();
    }
}
