<?php

declare(strict_types=1);

namespace App\Library\Infrastructure;

use App\Library\Application\Book\Query\BookQueries;
use App\Library\Application\Clock;
use App\Library\Domain\Book\BookRepository;
use App\Library\Infrastructure\Persistence\Eloquent\Queries\EloquentBookQueries;
use App\Library\Infrastructure\Persistence\Eloquent\Repositories\EloquentBookRepository;
use Illuminate\Support\ServiceProvider;

final class LibraryServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->app->bind(BookRepository::class, EloquentBookRepository::class);
        $this->app->bind(BookQueries::class, EloquentBookQueries::class);
        $this->app->bind(Clock::class, SystemClock::class);
    }
}
