<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Auth;

use Illuminate\Console\Command;
use Laravel\Passport\ClientRepository;
use RuntimeException;

final class IssueToken extends Command
{
    protected $signature = 'library:token
        {email : The account the token acts for}
        {--resource= : The server the token is for (mcp); without it, the token opens nothing}';

    protected $description = 'Issue an access token for an account, for the hand-written client and the benchmark (never for Claude Code: it runs the OAuth flow)';

    public function handle(ClientRepository $clients, ResourceIndicator $indicator): int
    {
        $resource = $this->option('resource');
        $target = $resource === null ? null : ProtectedResource::tryFrom((string) $resource);

        if ($resource !== null && $target === null) {
            $this->error('Unknown resource: expected one of ' . implode(', ', array_column(ProtectedResource::cases(), 'value')) . '.');

            return self::FAILURE;
        }

        $account = User::query()->where('email', $this->argument('email'))->first();

        if (! $account instanceof User) {
            $this->error('No account with this e-mail.');

            return self::FAILURE;
        }

        try {
            $clients->personalAccessClient('users');
        } catch (RuntimeException) {
            $clients->createPersonalAccessGrantClient('Marque-page development', 'users');
        }

        $indicator->indicate($target);
        $this->line($account->createToken('development')->accessToken);

        return self::SUCCESS;
    }
}
