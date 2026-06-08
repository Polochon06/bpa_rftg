@extends('layouts.app')

@section('content')
<div style="min-height: calc(100vh - 54px); display: flex; align-items: center; justify-content: center; padding: 2rem;">
    <div style="width: 100%; max-width: 400px;">

        <div style="text-align: center; margin-bottom: 2rem;">
            <div style="font-size: 11px; font-weight: 700; letter-spacing: 0.25em; text-transform: uppercase; color: var(--c-accent); margin-bottom: 0.75rem;">
                {{ config('app.name', 'Mario') }}
            </div>
            <h1 style="font-size: 22px; font-weight: 700; margin: 0 0 0.4rem; color: var(--c-text);">Connexion</h1>
            <p style="font-size: 13px; color: var(--c-text-2); margin: 0;">Accédez au catalogue de films DVD</p>
        </div>

        <div class="card">
            <div class="card-body" style="padding: 2rem !important;">
                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="mb-4">
                        <label for="email" class="form-label">Adresse email</label>
                        <input id="email" type="email" name="email"
                               class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email') }}" required autofocus
                               placeholder="votre@email.com">
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Mot de passe</label>
                        <input id="password" type="password" name="password"
                               class="form-control @error('password') is-invalid @enderror"
                               required placeholder="••••••••">
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 13px; color: var(--c-text-2);">
                            <input type="checkbox" name="remember" style="accent-color: var(--c-accent); width: 14px; height: 14px;">
                            Se souvenir de moi
                        </label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" style="font-size: 12px; color: var(--c-text-2); text-decoration: none;">
                                Mot de passe oublié ?
                            </a>
                        @endif
                    </div>

                    <button type="submit" class="btn btn-primary w-100" style="padding: 0.6rem !important; font-size: 14px !important; letter-spacing: 0.03em !important;">
                        Se connecter
                    </button>
                </form>
            </div>
        </div>

        @if (Route::has('register'))
            <p style="text-align: center; margin-top: 1.25rem; font-size: 13px; color: var(--c-text-2);">
                Pas encore de compte ?
                <a href="{{ route('register') }}" style="color: var(--c-accent); text-decoration: none; font-weight: 500;">
                    Créer un compte
                </a>
            </p>
        @endif

    </div>
</div>
@endsection
