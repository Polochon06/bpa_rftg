@extends('layouts.app')

@section('content')

@if (session('status'))
    <div class="alert alert-success mb-4">{{ session('status') }}</div>
@endif

<div class="section-header">
    <div>
        <h1 class="section-title">Tableau de bord</h1>
        <p class="section-sub">Bienvenue, {{ Auth::user()->name ?? 'Utilisateur' }}</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body" style="padding: 1.5rem !important;">
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem;">
                    <div style="width: 38px; height: 38px; border-radius: var(--radius); background: var(--c-accent-dim); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="bi bi-film" style="color: var(--c-accent); font-size: 17px;"></i>
                    </div>
                    <div>
                        <div style="font-size: 14px; font-weight: 600; color: var(--c-text);">Catalogue de films</div>
                        <div style="font-size: 12px; color: var(--c-text-2);">Gérez votre catalogue DVD</div>
                    </div>
                </div>
                <div class="d-grid gap-2">
                    <a href="{{ route('films.index') }}" class="btn btn-primary">
                        <i class="bi bi-film me-2"></i>Voir tous les films
                    </a>
                    <a href="{{ route('films.create') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-plus me-2"></i>Ajouter un film
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body" style="padding: 1.5rem !important;">
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem;">
                    <div style="width: 38px; height: 38px; border-radius: var(--radius); background: rgba(58,128,192,0.10); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="bi bi-box-seam" style="color: var(--c-info); font-size: 17px;"></i>
                    </div>
                    <div>
                        <div style="font-size: 14px; font-weight: 600; color: var(--c-text);">Stock DVD</div>
                        <div style="font-size: 12px; color: var(--c-text-2);">Inventaire et disponibilité</div>
                    </div>
                </div>
                <div class="d-grid">
                    <a href="{{ route('stocks.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-box-seam me-2"></i>Gérer le stock DVD
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
