<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Api;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

final class CorsTest extends TestCase
{
    private const string FRONT = 'http://localhost:5173';

    #[Test]
    #[TestWith(['/api/books', 'GET'])]
    #[TestWith(['/oauth/token', 'POST'])]
    #[TestWith(['/.well-known/oauth-protected-resource/api', 'GET'])]
    public function the_front_may_call_the_api_the_token_endpoint_and_the_metadata(string $path, string $method): void
    {
        $this->call('OPTIONS', $path, server: [
            'HTTP_ORIGIN' => self::FRONT,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => $method,
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization, content-type',
        ])
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', self::FRONT)
            ->assertHeader('Access-Control-Allow-Headers', 'accept, authorization, content-type');
    }

    #[Test]
    public function the_front_can_read_where_to_get_a_token_from_a_401(): void
    {
        $this->getJson('/api/books', ['Origin' => self::FRONT])
            ->assertUnauthorized()
            ->assertHeader('Access-Control-Allow-Origin', self::FRONT)
            ->assertHeader('Access-Control-Expose-Headers', 'Location, WWW-Authenticate');
    }

    #[Test]
    public function another_origin_is_never_named_so_the_browser_refuses_it_the_response(): void
    {
        $this->getJson('/api/books', ['Origin' => 'https://evil.example'])
            ->assertUnauthorized()
            ->assertHeader('Access-Control-Allow-Origin', self::FRONT);
    }
}
