<?php

declare(strict_types=1);

namespace Tests\Feature\Library;

use App\Library\Infrastructure\Auth\ProtectedResource;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\ClientRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Support\Library\Accounts;
use Tests\Support\Library\Readers;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    use DatabaseTransactions;

    private const string EMAIL = Readers::ME . '@marque-page.test';

    #[Test]
    public function the_right_credentials_open_a_session_for_the_account(): void
    {
        $account = Accounts::of(Readers::ME);

        $this->post('/login', ['email' => self::EMAIL, 'password' => 'secret'])->assertRedirect('/');

        $this->assertAuthenticatedAs($account, 'web');
    }

    #[Test]
    public function once_logged_in_the_reader_goes_back_to_the_page_that_asked_for_it(): void
    {
        Accounts::of(Readers::ME);
        $client = $this->app->make(ClientRepository::class)
            ->createAuthorizationCodeGrantClient('Claude Code', ['http://localhost:33418/callback'], confidential: false);
        // Laravel remembers the intended URL with its query sorted by name.
        $asked = url('/oauth/authorize?' . http_build_query([
            'client_id' => $client->getKey(),
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', str_repeat('v', 64), true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            'redirect_uri' => 'http://localhost:33418/callback',
            'resource' => ProtectedResource::Mcp->uri(),
            'response_type' => 'code',
        ]));
        $this->get($asked)->assertRedirect(route('login'));

        $this->post('/login', ['email' => self::EMAIL, 'password' => 'secret'])->assertRedirect($asked);
    }

    #[Test]
    public function logging_in_starts_a_new_session(): void
    {
        Accounts::of(Readers::ME);
        $this->get('/login');
        $before = session()->getId();

        $this->post('/login', ['email' => self::EMAIL, 'password' => 'secret']);

        $this->assertNotSame($before, session()->getId());
    }

    #[Test]
    public function a_wrong_password_opens_nothing_and_says_so_without_telling_which_part_is_wrong(): void
    {
        Accounts::of(Readers::ME);

        $this->post('/login', ['email' => self::EMAIL, 'password' => 'not-the-secret'])
            ->assertSessionHasErrors(['email' => 'These credentials do not match any account.']);

        $this->assertGuest('web');
    }

    #[Test]
    public function an_unknown_account_gets_the_same_answer_as_a_wrong_password(): void
    {
        $this->post('/login', ['email' => 'nobody@marque-page.test', 'password' => 'secret'])
            ->assertSessionHasErrors(['email' => 'These credentials do not match any account.']);

        $this->assertGuest('web');
    }

    /**
     * @param array<string, mixed> $credentials
     */
    #[Test]
    #[TestWith([['password' => 'secret'], 'email'])]
    #[TestWith([['email' => 'not-an-email', 'password' => 'secret'], 'email'])]
    #[TestWith([['email' => self::EMAIL], 'password'])]
    #[TestWith([['email' => self::EMAIL, 'password' => ['secret']], 'password'])]
    public function malformed_credentials_are_refused_field_by_field(array $credentials, string $field): void
    {
        Accounts::of(Readers::ME);

        $this->post('/login', $credentials)->assertSessionHasErrors([$field]);

        $this->assertGuest('web');
    }
}
