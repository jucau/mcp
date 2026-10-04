@extends('auth.layout')

@section('title', 'connexion')

@section('content')
    <p>Connectez-vous pour autoriser l'accès à votre bibliothèque.</p>

    @error('email')
        <p class="error">{{ $message }}</p>
    @enderror

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>

        <label for="password">Mot de passe</label>
        <input id="password" name="password" type="password" required>

        <button type="submit" class="primary">Se connecter</button>
    </form>
@endsection
