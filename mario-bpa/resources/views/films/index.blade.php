
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
            @endif

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Gestion du catalogue de films</h5>
                    <a href="{{ route('films.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Ajouter un film
                    </a>
                </div>

                <div class="card-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text" title="Rechercher">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" 
                                       class="form-control" 
                                       id="searchInput" 
                                       placeholder="Rechercher par titre ou description..."
                                       aria-label="Rechercher un film">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex gap-2">
                                <select class="form-select"
                                        id="categoryFilter"
                                        aria-label="Filtrer par catégorie">
                                    <option value="">Toutes les catégories</option>
                                    @if(!empty($categories))
                                        @foreach($categories as $category)
                                            <option value="{{ $category['categoryId'] ?? $category['category_id'] ?? $category['id'] }}">
                                                {{ $category['name'] ?? 'Catégorie inconnue' }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                                <select class="form-select"
                                        id="yearFilter"
                                        aria-label="Filtrer par année">
                                    <option value="">Toutes les années</option>
                                    @php
                                        $years = collect($films)->pluck('releaseYear')->unique()->sort()->reverse();
                                    @endphp
                                    @foreach($years as $year)
                                        <option value="{{ $year }}">{{ $year }}</option>
                                    @endforeach
                                </select>
                                <select class="form-select"
                                        id="ratingFilter"
                                        aria-label="Filtrer par classification">
                                    <option value="">Toutes les classifications</option>
                                    <option value="G">G (Tout public)</option>
                                    <option value="PG">PG (Accord parental souhaitable)</option>
                                    <option value="PG-13">PG-13 (Accord parental nécessaire)</option>
                                    <option value="R">R (Restreint)</option>
                                </select>
                                <button type="button"
                                        id="filterButton"
                                        class="btn btn-primary">
                                    <i class="bi bi-funnel"></i> Filtrer
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Compteur de résultats et réinitialiser -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="text-muted">
                            <span id="totalResults">{{ count($films) }}</span> film(s) trouvé(s)
                        </div>
                        <div>
                            <button type="button" id="resetFilters" class="btn btn-link btn-sm text-muted">
                                <i class="bi bi-arrow-counterclockwise"></i> Réinitialiser les filtres
                            </button>
                        </div>
                    </div>

                    <!-- Message pour aucun résultat de recherche -->
                    <div id="noResultsMessage" class="alert alert-info d-none">
                        <i class="bi bi-info-circle me-2"></i>
                        Aucun film ne correspond à vos critères de recherche.
                    </div>

                    @if (empty($films))
                        <div class="alert alert-warning d-flex align-items-center gap-2">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <span>Aucun film disponible ou erreur lors de la récupération des données de l'API.</span>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th class="text-center">ID</th>
                                        <th>Titre</th>
                                        <th>Description</th>
                                        <th class="text-center">Année</th>
                                        <th class="text-center">Durée</th>
                                        <th class="text-center">Classification</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="filmsTableBody">
                                    @foreach ($films as $film)
                                        <tr>
                                            <td class="text-center">{{ $film['filmId'] ?? $film['id'] ?? 'N/A' }}</td>
                                            <td>
                                                <strong>{{ $film['title'] ?? 'Sans titre' }}</strong>
                                                @if($film['releaseYear'] ?? false)
                                                    <small class="text-muted d-block">Sortie en {{ $film['releaseYear'] }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="description-tooltip" data-bs-toggle="tooltip" 
                                                     title="{{ $film['description'] ?? 'Aucune description' }}">
                                                    {{ Str::limit($film['description'] ?? 'Aucune description', 80) }}
                                                </div>
                                            </td>
                                            <td class="text-center">{{ $film['releaseYear'] ?? 'N/A' }}</td>
                                            <td class="text-center">
                                                @if(isset($film['length']) && $film['length'] > 0)
                                                    <span class="badge bg-secondary">
                                                        <i class="bi bi-clock"></i> {{ $film['length'] }} min
                                                    </span>
                                                @else
                                                    <span class="text-muted">N/A</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if(isset($film['rating']))
                                                    <span class="badge {{ match($film['rating']) {
                                                        'G' => 'bg-success',
                                                        'PG' => 'bg-info',
                                                        'PG-13' => 'bg-warning',
                                                        'R' => 'bg-danger',
                                                        default => 'bg-secondary'
                                                    } }}">
                                                        {{ $film['rating'] }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">N/A</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group">
                                                    <a href="{{ route('films.show', ['id' => $film['filmId'] ?? $film['id']]) }}"
                                                       class="btn btn-sm btn-outline-primary"
                                                       title="Voir les détails">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="{{ route('stocks.show', ['filmId' => $film['filmId'] ?? $film['id']]) }}"
                                                       class="btn btn-sm btn-outline-info"
                                                       title="Gérer le stock">
                                                        <i class="bi bi-box-seam"></i>
                                                    </a>
                                                    <a href="{{ route('films.edit', ['id' => $film['filmId'] ?? $film['id']]) }}"
                                                       class="btn btn-sm btn-outline-secondary"
                                                       title="Modifier">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <form action="{{ route('films.destroy', ['id' => $film['filmId'] ?? $film['id']]) }}"
                                                          method="POST"
                                                          style="display: inline;"
                                                          onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer le film \'{{ addslashes($film['title']) }}\' ?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit"
                                                                class="btn btn-sm btn-outline-danger"
                                                                title="Supprimer">
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

                        <div class="mt-4">
                                <p class="text-muted mb-0">
                                <i class="bi bi-info-circle-fill"></i> 
                                Films affichés : <strong id="totalResults">{{ count($films) }}</strong>
                                sur <strong>{{ count($films) }}</strong> au total
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour les détails du film -->
<div class="modal fade" id="filmDetailsModal" tabindex="-1" aria-labelledby="filmDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="filmDetailsModalLabel">
                    <i class="bi bi-film me-2"></i>Détails du film
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <div class="card border-0">
                    <div class="card-body px-0">
                        <h3 id="modalFilmTitle" class="card-title mb-4"></h3>
                        
                        <div class="row mb-4">
                            <div class="col">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-2">
                                            <i class="bi bi-info-circle me-2"></i>Description
                                        </h6>
                                        <p id="modalFilmDescription" class="card-text"></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-4">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <h6 class="card-subtitle mb-2">
                                            <i class="bi bi-calendar-event me-2"></i>Année
                                        </h6>
                                        <p id="modalFilmYear" class="card-text display-6"></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <h6 class="card-subtitle mb-2">
                                            <i class="bi bi-clock me-2"></i>Durée
                                        </h6>
                                        <p id="modalFilmLength" class="card-text display-6"></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <h6 class="card-subtitle mb-2">
                                            <i class="bi bi-badge-tm me-2"></i>Classification
                                        </h6>
                                        <p id="modalFilmRating" class="card-text display-6"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-2"></i>Fermer
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Le JavaScript pour les filtres et boutons est géré dans resources/js/films.js
console.log('Page films/index chargée, films.js devrait être actif');
</script>

<style>
@media print {
    .btn-group, .card-header, .input-group, form, .pagination {
        display: none !important;
    }
    .table {
        border: 1px solid #dee2e6;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
    }
}

.stars-rating {
    color: #6c757d;
}

.description-tooltip {
    cursor: help;
}

/* Animation pour les badges */
.badge {
    transition: all 0.3s ease;
}
.badge:hover {
    transform: scale(1.1);
}

/* Style personnalisé pour la pagination */
.pagination {
    gap: 5px;
}
.page-link {
    border-radius: 4px;
}

/* Animation pour les boutons d'action */
.btn-group .btn {
    transition: all 0.2s ease;
}
.btn-group .btn:hover {
    transform: translateY(-2px);
}
</style>
@endpush

@endsection