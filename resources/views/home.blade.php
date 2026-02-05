@extends('layouts.app')

@section('content')
<div class="container">
    @if (session('status'))
        <div class="alert alert-success" role="alert">
            {{ session('status') }}
        </div>
    @endif

    <div class="row">
        <div class="col-md-12 mb-4">
            <h2>Tableau de bord</h2>
            <p class="text-muted">Bienvenue, {{ Auth::user()->name ?? 'Utilisateur' }} !</p>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Gestion des films</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('films.index') }}" class="btn btn-primary">
                            <i class="bi bi-film"></i> Voir tous les films
                        </a>
                        <a href="{{ route('films.create') }}" class="btn btn-success">
                            <i class="bi bi-plus-circle"></i> Ajouter un nouveau film
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Liens rapides</h5>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <a href="{{ route('welcome') }}" class="text-decoration-none">
                            <i class="bi bi-house"></i> Page d'accueil
                        </a>
                    </li>
                    <li class="list-group-item">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-link text-danger p-0">
                                <i class="bi bi-box-arrow-right"></i> Se déconnecter
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
