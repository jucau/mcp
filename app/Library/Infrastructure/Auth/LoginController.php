<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Auth;

use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final readonly class LoginController
{
    public function __construct(private Factory $auth)
    {
    }

    public function show(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! $this->auth->guard('web')->attempt($request->only(['email', 'password']))) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match any account.']);
        }

        $request->session()->regenerate();

        return redirect()->intended('/');
    }
}
