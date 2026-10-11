<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Api;

use App\Library\Application\Book\Catalog\CatalogUnavailable;
use App\Library\Domain\Book\BookLent;
use App\Library\Domain\Refusal;
use App\Library\Infrastructure\Api\ProblemDetails;
use DomainException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RefusalsTest extends TestCase
{
    #[Test]
    public function every_refusal_of_the_library_has_an_http_status(): void
    {
        $refusals = [];

        foreach ([...File::allFiles(app_path('Library/Domain')), ...File::allFiles(app_path('Library/Application'))] as $file) {
            $class = 'App\\' . str_replace('/', '\\', substr($file->getPathname(), strlen(app_path()) + 1, -4));

            if (is_subclass_of($class, Refusal::class)) {
                $refusals[$class] = ProblemDetails::statusOf($class);
            }
        }

        $this->assertArrayHasKey(BookLent::class, $refusals);
        $this->assertArrayHasKey(CatalogUnavailable::class, $refusals);
        $this->assertSame([], array_filter($refusals, static fn (int $status): bool => $status < 400));
    }

    #[Test]
    public function an_exception_that_is_no_refusal_is_a_failure_whose_message_stays_inside(): void
    {
        config(['app.debug' => false]);
        Route::get('api/not-a-refusal', static fn () => throw new DomainException('Something a dependency said.'));

        $this->getJson('/api/not-a-refusal')
            ->assertInternalServerError()
            ->assertExactJson(['message' => 'Server Error']);
    }
}
