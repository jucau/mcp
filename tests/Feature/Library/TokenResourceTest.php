<?php

declare(strict_types=1);

namespace Tests\Feature\Library;

use App\Library\Infrastructure\Auth\ProtectedResource;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Library\Accounts;
use Tests\Support\Library\Readers;
use Tests\TestCase;
use Webmozart\Assert\Assert;

final class TokenResourceTest extends TestCase
{
    use DatabaseTransactions;

    private const string CALLBACK = 'http://localhost:33418/callback';

    private const string VERIFIER = 'vvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvv';

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->app->make(ClientRepository::class)
            ->createAuthorizationCodeGrantClient('Claude Code', [self::CALLBACK], confidential: false);
        $this->actingAs(Accounts::of(Readers::ME), 'web');
    }

    #[Test]
    public function an_authorization_that_names_no_server_is_refused(): void
    {
        $this->authorize(resource: null)
            ->assertBadRequest()
            ->assertJsonPath('error', 'invalid_target');
    }

    #[Test]
    public function an_authorization_for_another_address_of_the_same_machine_is_refused(): void
    {
        $this->authorize(resource: 'http://127.0.0.1:8090/mcp')
            ->assertBadRequest()
            ->assertJsonPath('error', 'invalid_target');
    }

    #[Test]
    public function the_token_of_an_approved_authorization_opens_the_server_it_names(): void
    {
        $token = $this->exchange($this->approve())->assertOk()->json('access_token');
        Assert::string($token);

        $this->assertSame(ProtectedResource::Mcp->uri(), $this->resourceOf($token));
        $this->mcpWith($token)->assertOk();
    }

    #[Test]
    public function a_code_cannot_be_exchanged_for_another_server_than_the_one_approved(): void
    {
        $this->exchange($this->approve(), resource: 'http://localhost:8090/elsewhere')
            ->assertBadRequest()
            ->assertJsonPath('error', 'invalid_target');
    }

    #[Test]
    public function an_authorization_already_granted_still_names_its_server(): void
    {
        $this->exchange($this->approve())->assertOk();

        $token = $this->exchange($this->codeFrom($this->authorize(ProtectedResource::Mcp->uri())))->json('access_token');
        Assert::string($token);

        $this->mcpWith($token)->assertOk();
    }

    #[Test]
    public function a_refreshed_token_keeps_the_server_of_the_one_it_replaces(): void
    {
        $refreshToken = $this->exchange($this->approve())->json('refresh_token');

        $token = $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->client->getKey(),
        ])->assertOk()->json('access_token');
        Assert::string($token);

        $this->mcpWith($token)->assertOk();
    }

    #[Test]
    public function a_token_issued_for_no_server_opens_none(): void
    {
        Artisan::call('library:token', ['email' => Accounts::of(Readers::ME)->email]);

        $this->mcpWith(trim(Artisan::output()))
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate');
    }

    #[Test]
    public function the_metadata_name_the_application_url_whatever_host_the_request_carries(): void
    {
        $this->getJson('http://evil.example/.well-known/oauth-protected-resource/mcp')
            ->assertOk()
            ->assertJson([
                'resource' => ProtectedResource::Mcp->uri(),
                'authorization_servers' => [config('app.url')],
            ]);
    }

    /**
     * @return TestResponse<Response>
     */
    private function authorize(?string $resource): TestResponse
    {
        return $this->get('/oauth/authorize?' . http_build_query(array_filter([
            'client_id' => $this->client->getKey(),
            'redirect_uri' => self::CALLBACK,
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'state',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', self::VERIFIER, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            'resource' => $resource,
        ])));
    }

    private function approve(): string
    {
        $this->authorize(ProtectedResource::Mcp->uri())->assertOk();

        return $this->codeFrom($this->post('/oauth/authorize', [
            'client_id' => $this->client->getKey(),
            'auth_token' => session('authToken'),
        ]));
    }

    /**
     * @param TestResponse<Response> $redirect
     */
    private function codeFrom(TestResponse $redirect): string
    {
        $location = $redirect->assertRedirect()->headers->get('Location');
        Assert::string($location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        Assert::string($query['code'] ?? null, 'No code in the redirection: ' . $location);

        return $query['code'];
    }

    /**
     * @return TestResponse<JsonResponse>
     */
    private function exchange(string $code, ?string $resource = null): TestResponse
    {
        return $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $this->client->getKey(),
            'redirect_uri' => self::CALLBACK,
            'code_verifier' => self::VERIFIER,
            'code' => $code,
            'resource' => $resource ?? ProtectedResource::Mcp->uri(),
        ]);
    }

    private function resourceOf(string $token): mixed
    {
        $claims = json_decode(base64_decode(strtr(explode('.', $token)[1], '-_', '+/')), true);
        Assert::isArray($claims);

        return Passport::token()->newQuery()->whereKey($claims['jti'])->value('resource');
    }

    /**
     * @return TestResponse<JsonResponse>
     */
    private function mcpWith(string $token): TestResponse
    {
        return $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => ['_meta' => [
                'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                'io.modelcontextprotocol/clientCapabilities' => new \stdClass(),
            ]],
        ], [
            'Authorization' => 'Bearer ' . $token,
            'MCP-Protocol-Version' => '2026-07-28',
            'Mcp-Method' => 'tools/list',
        ]);
    }
}
