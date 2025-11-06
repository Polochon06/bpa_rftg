
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
                    <!-- Barre de recherche et filtres -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" id="searchInput" placeholder="Rechercher un film...">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex gap-2">
                                <select class="form-select" id="yearFilter">
                                    <option value="">Année</option>
                                    @foreach(range(date('Y'), 1900) as $year)
                                        <option value="{{ $year }}">{{ $year }}</option>
                                    @endforeach
                                </select>
                                <select class="form-select" id="ratingFilter">
                                    <option value="">Note</option>
                                    @foreach(range(5, 1) as $rating)
                                        <option value="{{ $rating }}">{{ $rating }}+ étoiles</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
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
                                        <th class="text-center">Note</th>
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
                                                    <div class="stars-rating" data-rating="{{ $film['rating'] }}">
                                                        @for($i = 1; $i <= 5; $i++)
                                                            <i class="bi bi-star{{ $i <= $film['rating'] ? '-fill text-warning' : '' }}"></i>
                                                        @endfor
                                                    </div>
                                                @else
                                                    <span class="badge bg-secondary">N/A</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group">
                                                    <a href="{{ route('films.show', $film['filmId'] ?? $film['id']) }}" 
                                                       class="btn btn-sm btn-outline-info" 
                                                       data-bs-toggle="tooltip" 
                                                       title="Voir les détails">
                                                        <i class="bi bi-eye-fill"></i>
                                                    </a>
                                                    <a href="{{ route('films.edit', $film['filmId'] ?? $film['id']) }}" 
                                                       class="btn btn-sm btn-outline-warning"
                                                       data-bs-toggle="tooltip" 
                                                       title="Modifier le film">
                                                        <i class="bi bi-pencil-fill"></i>
                                                    </a>
                                                    <form action="{{ route('films.destroy', $film['filmId'] ?? $film['id']) }}" 
                                                          method="POST" 
                                                          class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" 
                                                                class="btn btn-sm btn-outline-danger delete-film"
                                                                data-bs-toggle="tooltip"
                                                                title="Supprimer le film">
                                                            <i class="bi bi-trash-fill"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 d-flex justify-content-between align-items-center">
                            <p class="text-muted mb-0">
                                <i class="bi bi-info-circle-fill"></i> 
                                Total : <strong>{{ count($films) }}</strong> film(s) 
                                <span class="filtered-count"></span>
                            </p>
                            <div class="btn-group">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="exportCSV">
                                    <i class="bi bi-file-earmark-spreadsheet"></i> Exporter en CSV
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="printTable">
                                    <i class="bi bi-printer"></i> Imprimer
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Activation des tooltips Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Gestionnaire de recherche
    const searchInput = document.getElementById('searchInput');
    const yearFilter = document.getElementById('yearFilter');
    const ratingFilter = document.getElementById('ratingFilter');
    const tableBody = document.getElementById('filmsTableBody');

    function filterTable() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedYear = yearFilter.value;
        const selectedRating = ratingFilter.value;

        const rows = tableBody.getElementsByTagName('tr');
        Array.from(rows).forEach(row => {
            const title = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
            const year = row.querySelector('td:nth-child(4)').textContent;
            const ratingStars = row.querySelector('.stars-rating');
            const rating = ratingStars ? ratingStars.getAttribute('data-rating') : 0;

            const matchesSearch = title.includes(searchTerm);
            const matchesYear = !selectedYear || year === selectedYear;
            const matchesRating = !selectedRating || rating >= selectedRating;

            row.style.display = matchesSearch && matchesYear && matchesRating ? '' : 'none';
        });
    }

    let updateFilteredCount = () => {
        const visibleRows = Array.from(tableBody.getElementsByTagName('tr'))
            .filter(row => row.style.display !== 'none').length;
        const totalRows = tableBody.getElementsByTagName('tr').length;
        
        const filteredCountSpan = document.querySelector('.filtered-count');
        if (visibleRows !== totalRows) {
            filteredCountSpan.textContent = ` (${visibleRows} affichés)`;
        } else {
            filteredCountSpan.textContent = '';
        }
    };

    searchInput.addEventListener('input', () => {
        filterTable();
        updateFilteredCount();
    });
    
    yearFilter.addEventListener('change', () => {
        filterTable();
        updateFilteredCount();
    });
    
    ratingFilter.addEventListener('change', () => {
        filterTable();
        updateFilteredCount();
    });

    // Confirmation de suppression
    document.querySelectorAll('.delete-film').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const form = this.closest('form');
            const filmTitle = this.closest('tr').querySelector('td:nth-child(2) strong').textContent;
            
            if (confirm(`Êtes-vous sûr de vouloir supprimer le film "${filmTitle}" ?`)) {
                form.submit();
            }
        });
    });

    // Export CSV
    document.getElementById('exportCSV').addEventListener('click', function() {
        try {
            const visibleRows = Array.from(tableBody.getElementsByTagName('tr'))
                .filter(row => row.style.display !== 'none');

            if (visibleRows.length === 0) {
                alert('Aucune donnée à exporter');
                return;
            }

            let csv = '\uFEFF'; // BOM pour Excel
            csv += 'ID,Titre,Description,Année,Durée,Note\n';
            
            visibleRows.forEach(row => {
                const cells = row.getElementsByTagName('td');
                const values = [
                    cells[0].textContent.trim(),
                    `"${cells[1].querySelector('strong').textContent.replace(/"/g, '""')}"`,
                    `"${cells[2].querySelector('.description-tooltip').getAttribute('title').replace(/"/g, '""')}"`,
                    cells[3].textContent.trim(),
                    cells[4].textContent.includes('min') ? cells[4].textContent.replace(/[^0-9]/g, '') : 'N/A',
                    cells[5].querySelector('.stars-rating') ? 
                        cells[5].querySelector('.stars-rating').getAttribute('data-rating') : 'N/A'
                ];
                csv += values.join(',') + '\n';
            });

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `films_${new Date().toISOString().slice(0, 10)}.csv`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(link.href);

        } catch (error) {
            console.error('Erreur lors de l\'export CSV:', error);
            alert('Une erreur est survenue lors de l\'export CSV');
        }
    });

    // Impression
    document.getElementById('printTable').addEventListener('click', function() {
        window.print();
    });
});
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