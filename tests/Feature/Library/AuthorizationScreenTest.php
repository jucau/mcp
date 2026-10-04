<?php

declare(strict_types=1);

namespace Tests\Feature\Library;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Library\Accounts;
use Tests\Support\Library\Readers;
use Tests\TestCase;

final class AuthorizationScreenTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function a_guest_is_first_asked_to_log_in(): void
    {
        $this->authorize($this->client())->assertRedirect(route('login'));
    }

    #[Test]
    public function the_reader_sees_which_client_asks_for_which_account_and_can_answer(): void
    {
        $this->actingAs(Accounts::of(Readers::ME), 'web');

        $this->authorize($this->client())
            ->assertOk()
            ->assertSeeInOrder(['Claude Code', 'demande l\'accès à votre bibliothèque', Readers::ME . '@marque-page.test'])
            ->assertSee(route('passport.authorizations.approve'))
            ->assertSee(route('passport.authorizations.deny'))
            ->assertSee('Autoriser')
            ->assertSee('Refuser');
    }

    #[Test]
    public function the_login_page_needs_no_compiled_asset(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('build/');
    }

    private function client(): Client
    {
        return $this->app->make(ClientRepository::class)
            ->createAuthorizationCodeGrantClient('Claude Code', ['http://localhost:33418/callback'], confidential: false);
    }

    /**
     * @return TestResponse<Response>
     */
    private function authorize(Client $client): TestResponse
    {
        $verifier = str_repeat('v', 64);

        return $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->getKey(),
            'redirect_uri' => 'http://localhost:33418/callback',
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'state',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]));
    }
}
