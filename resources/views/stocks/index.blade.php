@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4>Gestion du Stock de DVD</h4>
                </div>

                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <!-- Barre de recherche -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <form action="{{ route('stocks.index') }}" method="GET" class="d-flex">
                                <input type="text" name="search" class="form-control" placeholder="Rechercher un film..." value="{{ $search }}">
                                <button type="submit" class="btn btn-primary ms-2">Rechercher</button>
                                @if($search)
                                    <a href="{{ route('stocks.index') }}" class="btn btn-secondary ms-2">Réinitialiser</a>
                                @endif
                            </form>
                        </div>
                    </div>

                    <!-- Tableau des stocks -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Titre</th>
                                    <th>Total</th>
                                    <th>Disponible</th>
                                    <th>Louée</th>
                                    <th>Prix</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($stocks as $stock)
                                    <tr>
                                        <td>
                                            <strong>{{ $stock->film['title'] ?? 'Film inconnu' }}</strong>
                                        </td>
                                        <td>{{ $stock->quantite_totale }}</td>
                                        <td>
                                            <span class="badge bg-{{ $stock->quantite_disponible > 0 ? 'success' : 'danger' }}">
                                                {{ $stock->quantite_disponible }}
                                            </span>
                                        </td>
                                        <td>{{ $stock->quantite_louee }}</td>
                                        <td>{{ number_format($stock->prix_location, 2) }} €</td>
                                        <td>
                                            <button class="btn btn-sm btn-success" onclick="ouvrirModalReception('{{ $stock->filmId }}', '{{ addslashes($stock->film['title'] ?? '') }}')">
                                                Réception
                                            </button>
                                            <button class="btn btn-sm btn-warning" onclick="ouvrirModalRetrait('{{ $stock->filmId }}', '{{ addslashes($stock->film['title'] ?? '') }}', {{ $stock->quantite_disponible }})">
                                                Retrait
                                            </button>
                                            <a href="{{ route('stocks.show', $stock->filmId) }}" class="btn btn-sm btn-info">
                                                Historique
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">Aucun stock trouvé</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Réception -->
<div class="modal fade" id="modalReception" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('stocks.reception') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Réceptionner des DVD</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="filmId" id="reception_filmId">

                    <div class="mb-3">
                        <label class="form-label">Film</label>
                        <input type="text" class="form-control" id="reception_filmTitle" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Quantité reçue *</label>
                        <input type="number" name="quantite" class="form-control" min="1" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Prix d'achat unitaire</label>
                        <input type="number" name="prix_achat" class="form-control" step="0.01" min="0">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">Valider</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Retrait -->
<div class="modal fade" id="modalRetrait" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('stocks.retrait') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Retirer du stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="filmId" id="retrait_filmId">

                    <div class="mb-3">
                        <label class="form-label">Film</label>
                        <input type="text" class="form-control" id="retrait_filmTitle" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Quantité *</label>
                        <input type="number" name="quantite" class="form-control" min="1" id="retrait_quantite" required>
                        <small class="text-muted">Disponible: <span id="retrait_dispo"></span></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Motif *</label>
                        <select name="motif" class="form-control" required>
                            <option value="">Sélectionner...</option>
                            <option value="casse">Casse / DVD endommagé</option>
                            <option value="perte">Perte</option>
                            <option value="vol">Vol</option>
                            <option value="obsolete">Obsolète / Destruction</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning">Confirmer le retrait</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function ouvrirModalReception(filmId, filmTitle) {
    document.getElementById('reception_filmId').value = filmId;
    document.getElementById('reception_filmTitle').value = filmTitle;

    const modal = new bootstrap.Modal(document.getElementById('modalReception'));
    modal.show();
}

function ouvrirModalRetrait(filmId, filmTitle, dispo) {
    document.getElementById('retrait_filmId').value = filmId;
    document.getElementById('retrait_filmTitle').value = filmTitle;
    document.getElementById('retrait_dispo').textContent = dispo;
    document.getElementById('retrait_quantite').max = dispo;

    const modal = new bootstrap.Modal(document.getElementById('modalRetrait'));
    modal.show();
}
</script>
@endsection
