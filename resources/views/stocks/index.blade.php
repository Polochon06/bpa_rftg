@extends('layouts.app')

@section('content')

<div class="section-header">
    <div>
        <h1 class="section-title">Stock DVD</h1>
        <p class="section-sub">Inventaire et disponibilite</p>
    </div>
</div>

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

<div class="card mb-4">
    <div class="card-body" style="padding: 0.9rem 1.25rem !important;">
        <form action="{{ route('stocks.index') }}" method="GET"
              style="display: flex; align-items: center; gap: 0.5rem;">
            <div class="input-group" style="max-width: 420px;">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control"
                       placeholder="Rechercher un film..." value="{{ $search }}">
            </div>
            <button type="submit" class="btn btn-primary">Rechercher</button>
            @if($search)
                <a href="{{ route('stocks.index') }}" class="btn btn-outline-secondary">Reinitialiser</a>
            @endif
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th style="width: 90px;">ID</th>
                    <th>Titre</th>
                    <th style="width: 80px; text-align: center;">Annee</th>
                    <th style="width: 90px; text-align: center;">Duree</th>
                    <th style="width: 110px; text-align: center;">Stock</th>
                </tr>
            </thead>
            <tbody>
                @forelse($films as $film)
                    @php $filmId = $film['filmId'] ?? $film['id'] ?? null; @endphp
                    <tr>
                        <td>
                            <span style="font-size: 11px; color: var(--c-text-3); font-family: monospace;">
                                {{ $filmId }}
                            </span>
                        </td>
                        <td style="font-weight: 500;">{{ $film['title'] ?? '—' }}</td>
                        <td style="text-align: center; color: var(--c-text-2); font-size: 13px;">
                            {{ $film['releaseYear'] ?? '—' }}
                        </td>
                        <td style="text-align: center; color: var(--c-text-2); font-size: 13px;">
                            {{ $film['length'] ?? '—' }} min
                        </td>
                        <td style="text-align: center;">
                            <a href="{{ route('stocks.show', $filmId) }}"
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-box-seam me-1"></i>Stock
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--c-text-3);
                                               padding: 2.5rem !important; font-size: 13px;">
                            @if($search)
                                Aucun film trouve pour "{{ $search }}"
                            @else
                                Aucun film disponible
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
