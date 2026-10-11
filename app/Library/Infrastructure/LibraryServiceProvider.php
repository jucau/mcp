<?php

declare(strict_types=1);

namespace App\Library\Infrastructure;

use App\Library\Application\Book\Catalog\BookCatalog;
use App\Library\Application\Book\Query\BookQueries;
use App\Library\Application\Clock;
use App\Library\Domain\Book\BookRepository;
use App\Library\Infrastructure\Api\OpenApi\DescribeTheApi;
use App\Library\Infrastructure\Api\OpenApi\QueryParametersAreNeverNull;
use App\Library\Infrastructure\Auth\IssueToken;
use App\Library\Infrastructure\Auth\Passport\AccessTokenRepository;
use App\Library\Infrastructure\Auth\Passport\ApproveAuthorizationController;
use App\Library\Infrastructure\Auth\Passport\AuthCodeRepository;
use App\Library\Infrastructure\Auth\Passport\AuthorizationController;
use App\Library\Infrastructure\Auth\Passport\ScopeRepository;
use App\Library\Infrastructure\Auth\ResourceIndicator;
use App\Library\Infrastructure\Catalog\FakeBookCatalog;
use App\Library\Infrastructure\Catalog\OpenLibraryBookCatalog;
use App\Library\Infrastructure\Persistence\Eloquent\Queries\EloquentBookQueries;
use App\Library\Infrastructure\Persistence\Eloquent\Repositories\EloquentBookRepository;
use App\Library\Infrastructure\Reader\CurrentReader;
use Carbon\CarbonInterval;
use Dedoc\Scramble\Scramble;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Response;
use Illuminate\Routing\UrlGenerator as RoutingUrlGenerator;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Bridge;
use Laravel\Passport\Http\Controllers;
use Laravel\Passport\Passport;
use LogicException;
use Webmozart\Assert\Assert;

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
        $this->app->bind(BookCatalog::class, function (Application $app): BookCatalog {
            $config = $app->make(Repository::class);
            $catalog = $config->get('library.catalog');

            return match (true) {
                $catalog === 'open-library' => $this->openLibrary($app, $config),
                $catalog === 'fake' && ! $app->environment('production') => new FakeBookCatalog(),
                $catalog === 'fake' => throw new LogicException('The fake catalog must never serve production.'),
                default => throw new LogicException('LIBRARY_CATALOG must be "open-library" or "fake".'),
            };
        });

        $this->app->singleton(ResourceIndicator::class);
        $this->app->bind(Bridge\AuthCodeRepository::class, AuthCodeRepository::class);
        $this->app->bind(Bridge\ScopeRepository::class, ScopeRepository::class);
        $this->app->bind(Bridge\AccessTokenRepository::class, AccessTokenRepository::class);
        $this->app->bind(Controllers\AuthorizationController::class, AuthorizationController::class);
        $this->app->when(AuthorizationController::class)->needs(StatefulGuard::class)
            ->give(function (Application $app): StatefulGuard {
                $guard = $app->make(AuthFactory::class)->guard();
                Assert::isInstanceOf($guard, StatefulGuard::class);

                return $guard;
            });
        $this->app->bind(Controllers\ApproveAuthorizationController::class, ApproveAuthorizationController::class);
    }

    private function openLibrary(Application $app, Repository $config): OpenLibraryBookCatalog
    {
        $url = $config->get('library.catalog_url');
        Assert::stringNotEmpty($url);

        return new OpenLibraryBookCatalog($app->make(HttpFactory::class), $url);
    }

    public function boot(Repository $config, UrlGenerator $url): void
    {
        $root = $config->get('app.url');
        Assert::stringNotEmpty($root);
        Assert::isInstanceOf($url, RoutingUrlGenerator::class);
        $url->forceRootUrl($root);

        $this->commands([IssueToken::class]);

        // Resolved once here so that a catalog misconfigured fails the boot, not the first lookup.
        $this->app->make(BookCatalog::class);

        if (class_exists(Scramble::class)) {
            Scramble::configure()
                ->withDocumentTransformers(DescribeTheApi::class)
                ->withOperationTransformers(QueryParametersAreNeverNull::class);
        }

        Passport::authorizationView(static fn (array $parameters): Response => response()->view('auth.authorize', $parameters));
        Passport::tokensExpireIn(CarbonInterval::hour());
        Passport::refreshTokensExpireIn(CarbonInterval::days(30));
    }
}
