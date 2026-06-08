@extends('layouts.app')

@section('content')

@php $filmId = $film['filmId'] ?? $film['id'] ?? null; @endphp

<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('stocks.index') }}">Stock DVD</a></li>
        <li class="breadcrumb-item active">{{ $film['title'] ?? 'Film' }}</li>
    </ol>
</nav>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show mb-4">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="section-header">
    <div>
        <h1 class="section-title">{{ $film['title'] ?? 'Film inconnu' }}</h1>
        <p class="section-sub">Gestion du stock DVD</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-success btn-sm"
                data-bs-toggle="modal" data-bs-target="#modalReception">
            <i class="bi bi-plus me-1"></i>Ajouter des DVD
        </button>
        <button class="btn btn-warning btn-sm"
                data-bs-toggle="modal" data-bs-target="#modalRetrait"
                @if(($stock->quantite_disponible ?? 0) === 0) disabled @endif>
            <i class="bi bi-dash me-1"></i>Retirer des DVD
        </button>
    </div>
</div>

{{-- Statistiques --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-block">
            <div class="stat-value">{{ $stock->quantite_totale ?? 0 }}</div>
            <div class="stat-label">Total d'exemplaires</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-block">
            <div class="stat-value"
                 style="color: {{ ($stock->quantite_disponible ?? 0) > 0 ? 'var(--c-success)' : 'var(--c-danger)' }};">
                {{ $stock->quantite_disponible ?? 0 }}
            </div>
            <div class="stat-label">Disponibles</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-block">
            <div class="stat-value" style="color: var(--c-info);">{{ $stock->quantite_louee ?? 0 }}</div>
            <div class="stat-label">Occupes (loues / panier)</div>
        </div>
    </div>
</div>

{{-- Tableau des exemplaires --}}
<div class="card">
    <div class="card-header" style="display: flex; align-items: center; gap: 0.5rem;">
        <i class="bi bi-list-ul" style="color: var(--c-accent);"></i>
        <span style="font-weight: 600; font-size: 14px;">Exemplaires</span>
        <span style="color: var(--c-text-2); font-size: 13px;">({{ count($inventaires) }})</span>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>ID Exemplaire</th>
                    <th>Magasin</th>
                    <th>Statut</th>
                    <th>Derniere mise a jour</th>
                </tr>
            </thead>
            <tbody>
                @forelse($inventaires as $inv)
                    @php
                        $invId    = $inv['inventoryId'] ?? $inv['inventory_id'] ?? null;
                        $estDispo = $invId !== null && isset($disponiblesIds[(string) $invId]);
                    @endphp
                    <tr>
                        <td style="font-family: monospace; font-size: 12px; color: var(--c-text-2);">
                            #{{ $invId ?? '—' }}
                        </td>
                        <td>Magasin {{ $inv['storeId'] ?? $inv['store_id'] ?? '—' }}</td>
                        <td>
                            @if($estDispo)
                                <span style="font-size: 11px; font-weight: 700; color: var(--c-success);
                                             letter-spacing: 0.06em; text-transform: uppercase;">
                                    Disponible
                                </span>
                            @else
                                <span style="font-size: 11px; font-weight: 700; color: var(--c-info);
                                             letter-spacing: 0.06em; text-transform: uppercase;">
                                    Occupe
                                </span>
                            @endif
                        </td>
                        <td style="color: var(--c-text-2); font-size: 13px;">
                            {{ $inv['lastUpdate'] ?? $inv['last_update'] ?? '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align: center; color: var(--c-text-3);
                                               padding: 2.5rem !important; font-size: 13px;">
                            Aucun exemplaire enregistre
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    <a href="{{ route('stocks.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Retour a la liste
    </a>
</div>

{{-- Modal : Ajouter des DVD --}}
<div class="modal fade" id="modalReception" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('stocks.reception') }}" method="POST">
                @csrf
                <input type="hidden" name="filmId" value="{{ $filmId }}">

                <div class="modal-header">
                    <h5 class="modal-title" style="font-size: 15px; font-weight: 600;">
                        <i class="bi bi-plus-circle me-2" style="color: var(--c-success);"></i>
                        Ajouter des DVD au stock
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    @php
                        $staffStore = session('toad_user.staff.storeId')
                                   ?? session('toad_user.staff.store_id')
                                   ?? 1;
                    @endphp
                    <div class="mb-3">
                        <label class="form-label">Film</label>
                        <input type="text" class="form-control" value="{{ $film['title'] ?? '' }}" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantite *</label>
                        <input type="number" name="quantite" class="form-control" min="1" value="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Magasin</label>
                        <input type="number" name="storeId" class="form-control"
                               value="{{ $staffStore }}" min="1">
                        <div class="form-text">Magasin du staff connecte (modifiable)</div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">Valider</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal : Retirer des DVD --}}
<div class="modal fade" id="modalRetrait" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('stocks.retrait') }}" method="POST">
                @csrf
                <input type="hidden" name="filmId" value="{{ $filmId }}">

                <div class="modal-header">
                    <h5 class="modal-title" style="font-size: 15px; font-weight: 600;">
                        <i class="bi bi-dash-circle me-2" style="color: var(--c-warning);"></i>
                        Retirer des DVD du stock
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Film</label>
                        <input type="text" class="form-control" value="{{ $film['title'] ?? '' }}" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantite *</label>
                        <input type="number" name="quantite" class="form-control"
                               min="1" max="{{ $stock->quantite_disponible ?? 1 }}"
                               value="1" required>
                        <div class="form-text">
                            Disponibles : <strong style="color: var(--c-text);">{{ $stock->quantite_disponible ?? 0 }}</strong>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Motif *</label>
                        <select name="motif" class="form-select" required>
                            <option value="">-- Selectionner --</option>
                            <option value="casse">Casse / DVD endommage</option>
                            <option value="perte">Perte</option>
                            <option value="vol">Vol</option>
                            <option value="obsolete">Obsolete / Destruction</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning">Confirmer</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
