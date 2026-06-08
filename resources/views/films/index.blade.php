@extends('layouts.app')

@section('content')

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4">
        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show mb-4">
        <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="section-header">
    <div>
        <h1 class="section-title">Catalogue de films</h1>
        <p class="section-sub" id="totalResults">{{ count($films) }} film(s)</p>
    </div>
    <a href="{{ route('films.create') }}" class="btn btn-primary">
        <i class="bi bi-plus me-1"></i>Ajouter un film
    </a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Recherche</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="searchInput" placeholder="Titre, description...">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Categorie</label>
                <select class="form-select" id="categoryFilter">
                    <option value="">Toutes les categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat['categoryId'] ?? $cat['category_id'] ?? $cat['id'] }}">
                            {{ $cat['name'] ?? 'Inconnue' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Annee</label>
                <select class="form-select" id="yearFilter">
                    <option value="">Toutes</option>
                    @foreach(collect($films)->pluck('releaseYear')->unique()->sort()->reverse() as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Classification</label>
                <select class="form-select" id="ratingFilter">
                    <option value="">Toutes</option>
                    <option value="G">G</option>
                    <option value="PG">PG</option>
                    <option value="PG-13">PG-13</option>
                    <option value="R">R</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="button" id="filterButton" class="btn btn-primary w-100" title="Filtrer">
                    <i class="bi bi-funnel"></i>
                </button>
            </div>
        </div>
        <div style="margin-top: 0.6rem; text-align: right;">
            <button type="button" id="resetFilters" class="btn btn-link btn-sm"
                    style="font-size: 12px; color: var(--c-text-3) !important; padding: 0 !important;">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Reinitialiser les filtres
            </button>
        </div>
    </div>
</div>

<div id="noResultsMessage" class="alert alert-info d-none mb-4">
    <i class="bi bi-info-circle me-2"></i>Aucun film ne correspond a vos criteres.
</div>

@if (empty($films))
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        Aucun film disponible ou erreur lors de la recuperation des donnees.
    </div>
@else
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th style="width: 90px;">ID</th>
                        <th>Titre</th>
                        <th>Description</th>
                        <th style="width: 75px; text-align: center;">Annee</th>
                        <th style="width: 85px; text-align: center;">Duree</th>
                        <th style="width: 80px; text-align: center;">Note</th>
                        <th style="width: 130px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="filmsTableBody">
                    @foreach ($films as $film)
                        @php
                            $filmId      = $film['filmId'] ?? $film['id'] ?? null;
                            $filmTitle   = $film['title'] ?? 'Sans titre';
                            $deleteTitle = 'Supprimer ce film ?';
                            $ratingColor = match($film['rating'] ?? '') {
                                'G'     => 'var(--c-success)',
                                'PG'    => 'var(--c-info)',
                                'PG-13' => 'var(--c-warning)',
                                'R'     => 'var(--c-danger)',
                                default => 'var(--c-text-2)',
                            };
                        @endphp
                        <tr>
                            <td>
                                <span style="font-size: 11px; color: var(--c-text-3); font-family: monospace;">
                                    {{ $filmId }}
                                </span>
                            </td>
                            <td>
                                <span style="font-weight: 500;">{{ $filmTitle }}</span>
                                @if(($film['source'] ?? '') === 'local')
                                    <span class="badge bg-secondary ms-1" style="font-size: 10px; vertical-align: middle; opacity: 0.7;">local</span>
                                @endif
                            </td>
                            <td>
                                <span style="color: var(--c-text-2); font-size: 13px;"
                                      title="{{ $film['description'] ?? '' }}">
                                    {{ Str::limit($film['description'] ?? '—', 70) }}
                                </span>
                            </td>
                            <td style="text-align: center; color: var(--c-text-2); font-size: 13px;">
                                {{ $film['releaseYear'] ?? '—' }}
                            </td>
                            <td style="text-align: center;">
                                @if(isset($film['length']) && $film['length'] > 0)
                                    <span style="font-size: 12px; color: var(--c-text-2);">
                                        {{ $film['length'] }}<span style="color: var(--c-text-3);"> min</span>
                                    </span>
                                @else
                                    <span style="color: var(--c-text-3);">—</span>
                                @endif
                            </td>
                            <td style="text-align: center;">
                                @if(isset($film['rating']))
                                    <span style="font-size: 11px; font-weight: 700; color: {{ $ratingColor }}; letter-spacing: 0.06em;">
                                        {{ $film['rating'] }}
                                    </span>
                                @else
                                    <span style="color: var(--c-text-3);">—</span>
                                @endif
                            </td>
                            <td>
                                <div class="action-group" style="justify-content: center;">
                                    <a href="{{ route('films.show', $filmId) }}"
                                       class="btn btn-sm btn-outline-secondary" title="Voir">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('stocks.show', $filmId) }}"
                                       class="btn btn-sm btn-outline-info" title="Stock">
                                        <i class="bi bi-box-seam"></i>
                                    </a>
                                    <a href="{{ route('films.edit', $filmId) }}"
                                       class="btn btn-sm btn-outline-secondary" title="Modifier">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="{{ route('films.destroy', $filmId) }}"
                                          method="POST"
                                          onsubmit="return confirm('{{ $deleteTitle }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@push('scripts')
<script>
console.log('Page films/index chargee, films.js devrait etre actif');
</script>
@endpush

@endsection
