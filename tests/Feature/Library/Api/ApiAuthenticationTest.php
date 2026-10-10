<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Api;

use App\Library\Domain\Book\BookRepository;
use App\Library\Infrastructure\Auth\ProtectedResource;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Library\Accounts;
use Tests\Support\Library\Books;
use Tests\Support\Library\Readers;
use Tests\Support\Library\RespectsTheApiContract;
use Tests\Support\Library\Tokens;
use Tests\TestCase;

final class ApiAuthenticationTest extends TestCase
{
    use DatabaseTransactions;
    use RespectsTheApiContract;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTheApiContract();
    }

    #[Test]
    public function without_a_token_the_api_says_where_to_learn_how_to_get_one(): void
    {
        $this->getJson('/api/books')
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', sprintf('Bearer resource_metadata="%s"', url('/.well-known/oauth-protected-resource/api')));
    }

    #[Test]
    public function the_metadata_name_the_api_as_the_resource(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource/api')
            ->assertOk()
            ->assertJsonPath('resource', ProtectedResource::Api->uri());
    }

    #[Test]
    public function the_token_decides_the_library_served_never_the_configured_reader(): void
    {
        $repository = $this->app->make(BookRepository::class);
        $repository->save(Books::aBook('Dune', 'Frank Herbert'));
        $repository->save(Books::aBook('Fondation', 'Isaac Asimov', reader: Readers::someoneElse()));
        Tokens::actingAs(Accounts::of(Readers::SOMEONE_ELSE), ProtectedResource::Api);

        $this->getJson('/api/books')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Fondation');
    }

    #[Test]
    public function a_token_issued_for_the_mcp_server_does_not_open_the_api(): void
    {
        Tokens::actingAs(Accounts::of(Readers::ME), ProtectedResource::Mcp);

        $this->getJson('/api/books')->assertUnauthorized()->assertHeader('WWW-Authenticate');
    }

    #[Test]
    public function a_token_issued_for_the_api_does_not_open_the_mcp_server(): void
    {
        Tokens::actingAs(Accounts::of(Readers::ME), ProtectedResource::Api);

        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
    }
}
