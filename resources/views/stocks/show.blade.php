@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <!-- Fil d'Ariane -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('stocks.index') }}">Stocks</a></li>
                    <li class="breadcrumb-item active">{{ $film['title'] ?? 'Film' }}</li>
                </ol>
            </nav>

            <!-- Résumé du stock -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Stock - {{ $film['title'] ?? 'Film inconnu' }}</h4>
                    <div>
                        <button class="btn btn-success" onclick="ouvrirModalReception()">
                            <i class="bi bi-plus-circle"></i> Ajouter des DVD
                        </button>
                        <button class="btn btn-warning" onclick="ouvrirModalRetrait()">
                            <i class="bi bi-dash-circle"></i> Retirer des DVD
                        </button>
                    </div>
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

                    <div class="row text-center">
                        <div class="col-md-4">
                            <h3 class="text-success">{{ $stock->quantite_disponible }}</h3>
                            <small>Disponibles</small>
                        </div>
                        <div class="col-md-4">
                            <h3 class="text-primary">{{ $stock->quantite_louee }}</h3>
                            <small>Louées</small>
                        </div>
                        <div class="col-md-4">
                            <h3>{{ $stock->quantite_totale }}</h3>
                            <small>Total</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Historique des mouvements -->
            <div class="card">
                <div class="card-header">
                    <h5>Historique des mouvements</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Quantité</th>
                                    <th>Motif</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($historique as $mouvement)
                                    <tr>
                                        <td>{{ $mouvement->created_at->format('d/m/Y H:i') }}</td>
                                        <td>
                                            @switch($mouvement->type)
                                                @case('entree')
                                                    <span class="badge bg-success">Réception</span>
                                                    @break
                                                @case('sortie')
                                                    <span class="badge bg-danger">Sortie</span>
                                                    @break
                                                @case('location')
                                                    <span class="badge bg-primary">Location</span>
                                                    @break
                                                @case('retour')
                                                    <span class="badge bg-info">Retour</span>
                                                    @break
                                                @default
                                                    <span class="badge bg-secondary">{{ $mouvement->type }}</span>
                                            @endswitch
                                        </td>
                                        <td>
                                            @if($mouvement->quantite > 0)
                                                <span class="text-success">+{{ $mouvement->quantite }}</span>
                                            @else
                                                <span class="text-danger">{{ $mouvement->quantite }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $mouvement->motif ?? '-' }}</td>
                                        <td>{{ $mouvement->notes ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">Aucun mouvement enregistré</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <a href="{{ route('stocks.index') }}" class="btn btn-secondary">Retour à la liste</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Réception (Ajouter des DVD) -->
<div class="modal fade" id="modalReception" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('stocks.reception') }}" method="POST">
                @csrf
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Ajouter des DVD au stock</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="filmId" value="{{ $film['filmId'] ?? $film['id'] }}">

                    <div class="mb-3">
                        <label class="form-label">Film</label>
                        <input type="text" class="form-control" value="{{ $film['title'] ?? '' }}" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Quantité à ajouter *</label>
                        <input type="number" name="quantite" class="form-control" min="1" value="1" required>
                        <small class="text-muted">Nombre de DVD à réceptionner</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Prix d'achat unitaire (optionnel)</label>
                        <input type="number" name="prix_achat" class="form-control" step="0.01" min="0" placeholder="0.00">
                        <small class="text-muted">Prix d'achat d'un DVD</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes (optionnel)</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Informations sur la réception..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i> Ajouter au stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Retrait (Retirer des DVD) -->
<div class="modal fade" id="modalRetrait" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('stocks.retrait') }}" method="POST">
                @csrf
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">Retirer des DVD du stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="filmId" value="{{ $film['filmId'] ?? $film['id'] }}">

                    <div class="mb-3">
                        <label class="form-label">Film</label>
                        <input type="text" class="form-control" value="{{ $film['title'] ?? '' }}" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Quantité à retirer *</label>
                        <input type="number" name="quantite" class="form-control" min="1" max="{{ $stock->quantite_disponible }}" value="1" required>
                        <small class="text-muted">Disponible: <strong>{{ $stock->quantite_disponible }}</strong> DVD</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Motif *</label>
                        <select name="motif" class="form-control" required>
                            <option value="">-- Sélectionner un motif --</option>
                            <option value="casse">Casse / DVD endommagé</option>
                            <option value="perte">Perte</option>
                            <option value="vol">Vol</option>
                            <option value="obsolete">Obsolète / Destruction</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Détails du retrait..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-dash-circle"></i> Retirer du stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function ouvrirModalReception() {
    const modal = new bootstrap.Modal(document.getElementById('modalReception'));
    modal.show();
}

function ouvrirModalRetrait() {
    const modal = new bootstrap.Modal(document.getElementById('modalRetrait'));
    modal.show();
}
</script>
@endsection
