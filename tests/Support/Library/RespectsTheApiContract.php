<?php

declare(strict_types=1);

namespace Tests\Support\Library;

use Illuminate\Testing\TestResponse;
use Spectator\Spectator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every response of the API must be in its contract, and so must every request it accepts: a request refused on
 * purpose by a test is out of the contract by design.
 */
trait RespectsTheApiContract
{
    protected function setUpTheApiContract(): void
    {
        config(['spectator.sources.local.base_path' => base_path('docs/api'), 'spectator.path_prefix' => 'api']);
        Spectator::using('openapi.json');
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<string, string> $headers
     * @param int $options
     *
     * @return TestResponse<Response>
     */
    #[\Override]
    public function json($method, $uri, array $data = [], array $headers = [], $options = 0): TestResponse
    {
        $response = parent::json($method, $uri, $data, $headers, $options);

        if ($response->isSuccessful()) {
            $response->assertValidRequest();
        }

        $response->assertValidResponse();

        return $response;
    }
}
