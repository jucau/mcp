<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Api;

use App\Library\Infrastructure\Api\ProblemDetails;
use DomainException;
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
}
