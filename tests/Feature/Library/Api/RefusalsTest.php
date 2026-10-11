<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Api;

use App\Library\Infrastructure\Api\ProblemDetails;
use DomainException;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RefusalsTest extends TestCase
{
    #[Test]
    public function every_refusal_of_the_domain_has_an_http_status(): void
    {
        $refusals = [];

        foreach (glob(app_path('Library/Domain/*/*.php')) ?: [] as $file) {
            $class = 'App\\Library\\Domain\\' . basename(dirname($file)) . '\\' . basename($file, '.php');

            if (is_subclass_of($class, DomainException::class)) {
                $refusals[$class] = ProblemDetails::statusOf($class);
            }
        }

        $this->assertNotEmpty($refusals);
        $this->assertSame([], array_filter($refusals, static fn (int $status): bool => $status < 400));
    }

    #[Test]
    public function a_domain_exception_the_library_did_not_raise_is_a_failure_whose_message_stays_inside(): void
    {
        config(['app.debug' => false]);
        Route::get('api/not-a-refusal', static fn () => throw new DomainException('Something a dependency said.'));

        $this->getJson('/api/not-a-refusal')
            ->assertInternalServerError()
            ->assertExactJson(['message' => 'Server Error']);
    }
}
