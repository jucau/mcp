<?php

declare(strict_types=1);

namespace App\Library\Infrastructure;

use App\Library\Application\Book\Query\BookQueries;
use App\Library\Application\Clock;
use App\Library\Domain\Book\BookRepository;
use App\Library\Infrastructure\Auth\IssueToken;
use App\Library\Infrastructure\Persistence\Eloquent\Queries\EloquentBookQueries;
use App\Library\Infrastructure\Persistence\Eloquent\Repositories\EloquentBookRepository;
use App\Library\Infrastructure\Reader\CurrentReader;
use Carbon\CarbonInterval;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Response;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

final class LibraryServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->app->bind(BookRepository::class, EloquentBookRepository::class);
        $this->app->bind(BookQueries::class, EloquentBookQueries::class);
        // Read when today is asked, not when a handler is built: an entry point that names every date itself, like
        // the demo seeder, has no current reader, and must still get its handlers.
        $this->app->bind(Clock::class, fn (Application $app): SystemClock => new SystemClock(fn (): string => $app->make(CurrentReader::class)->timezone()));
    }

    public function boot(): void
    {
        $this->commands([IssueToken::class]);

        Passport::authorizationView(static fn (array $parameters): Response => response()->view('auth.authorize', $parameters));
        Passport::tokensExpireIn(CarbonInterval::hour());
        Passport::refreshTokensExpireIn(CarbonInterval::days(30));
    }
}
