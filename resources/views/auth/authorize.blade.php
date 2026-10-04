@extends('auth.layout')

@section('title', 'autoriser un accès')

@section('content')
    <p><strong>{{ $client->name }}</strong> demande l'accès à votre bibliothèque.</p>

    <div class="box">
        <p class="muted">Compte</p>
        <p>{{ $user->email }}</p>
    </div>

    @if (count($scopes) > 0)
        <div class="box">
            <p class="muted">Il pourra</p>
            <ul>
                @foreach ($scopes as $scope)
                    <li>{{ $scope->description }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <p class="muted">N'autorisez que si vous venez de demander cette connexion depuis votre client MCP.</p>

    <div class="actions">
        <form method="POST" action="{{ route('passport.authorizations.deny') }}">
            @csrf
            @method('DELETE')
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit">Refuser</button>
        </form>

        <form method="POST" action="{{ route('passport.authorizations.approve') }}">
            @csrf
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit" class="primary">Autoriser</button>
        </form>
    </div>
@endsection
