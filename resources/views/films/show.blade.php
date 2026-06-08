@extends('layouts.app')

@section('content')

@php
    $filmId      = $film['filmId'] ?? $film['id'] ?? null;
    $ratingColor = match($film['rating'] ?? '') {
        'G'     => 'var(--c-success)',
        'PG'    => 'var(--c-info)',
        'PG-13' => 'var(--c-warning)',
        'R'     => 'var(--c-danger)',
        default => 'var(--c-text-2)',
    };
@endphp

<a href="{{ route('films.index') }}" class="back-link">
    <i class="bi bi-arrow-left"></i> Retour au catalogue
</a>

<div class="row g-4">

    {{-- Colonne principale --}}
    <div class="col-md-8">

        @if(isset($film['rating']))
            <div style="font-size: 11px; font-weight: 700; color: {{ $ratingColor }}; letter-spacing: 0.14em; text-transform: uppercase; margin-bottom: 0.5rem;">
                {{ $film['rating'] }}
            </div>
        @endif

        <h1 style="font-size: 26px; font-weight: 700; margin: 0 0 0.25rem; line-height: 1.25;">
            {{ $film['title'] ?? 'Sans titre' }}
        </h1>

        @if($film['releaseYear'] ?? false)
            <p style="color: var(--c-text-2); font-size: 13px; margin: 0 0 1.5rem;">
                {{ $film['releaseYear'] }}
            </p>
        @endif

        <p style="color: var(--c-text-2); line-height: 1.75; font-size: 14px; margin-bottom: 1.75rem;">
            {{ $film['description'] ?? 'Aucune description disponible.' }}
        </p>

        <div class="row g-3 mb-4">
            <div class="col-4">
                <div class="stat-block">
                    <div class="stat-value" style="font-size: 1.6rem;">{{ $film['releaseYear'] ?? '—' }}</div>
                    <div class="stat-label">Annee</div>
                </div>
            </div>
            <div class="col-4">
                <div class="stat-block">
                    <div class="stat-value" style="font-size: 1.6rem;">
                        {{ ($film['length'] ?? 0) > 0 ? $film['length'] : '—' }}
                        @if(($film['length'] ?? 0) > 0)
                            <span style="font-size: 1rem; color: var(--c-text-2); font-weight: 400;"> min</span>
                        @endif
                    </div>
                    <div class="stat-label">Duree</div>
                </div>
            </div>
            <div class="col-4">
                <div class="stat-block">
                    <div class="stat-value" style="font-size: 1.4rem; color: {{ $ratingColor }};">
                        {{ $film['rating'] ?? '—' }}
                    </div>
                    <div class="stat-label">Classification</div>
                </div>
            </div>
        </div>

        <hr>

        <table style="width: 100%; border-collapse: collapse;">
            @foreach([
                ['ID',                    $filmId ?? '—'],
                ['Langue',                'ID ' . ($film['languageId'] ?? '—')],
                ['Cout de remplacement',  isset($film['replacementCost']) ? $film['replacementCost'] . ' EUR' : '—'],
                ['Caracteristiques',      $film['specialFeatures'] ?? 'Aucune'],
                ['Source',                $film['source'] ?? '—'],
                ['Derniere mise a jour',  $film['lastUpdate'] ?? '—'],
            ] as [$label, $value])
            <tr style="border-bottom: 1px solid var(--c-border);">
                <td style="padding: 0.6rem 0; color: var(--c-text-2); font-size: 11px; font-weight: 600;
                           letter-spacing: 0.07em; text-transform: uppercase; width: 38%;">
                    {{ $label }}
                </td>
                <td style="padding: 0.6rem 0; color: var(--c-text); font-size: 13px;">
                    {{ $value }}
                </td>
            </tr>
            @endforeach
        </table>

        <div class="d-flex gap-2 mt-4">
            <a href="{{ route('films.edit', $filmId) }}" class="btn btn-primary">
                <i class="bi bi-pencil me-1"></i>Modifier
            </a>
            <form action="{{ route('films.destroy', $filmId) }}" method="POST"
                  onsubmit="return confirm('Supprimer ce film ?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger">
                    <i class="bi bi-trash me-1"></i>Supprimer
                </button>
            </form>
        </div>
    </div>

    {{-- Colonne stock --}}
    <div class="col-md-4">
        <div class="card">
            <div class="card-header" style="display: flex; align-items: center; gap: 0.5rem;">
                <i class="bi bi-box-seam" style="color: var(--c-accent);"></i>
                <span style="font-weight: 600; font-size: 14px;">Disponibilite</span>
            </div>
            <div class="card-body">
                @if($stock)
                    <div class="row g-2 text-center mb-4">
                        <div class="col-4">
                            <div style="font-size: 1.6rem; font-weight: 700; line-height: 1;">
                                {{ $stock->quantite_totale }}
                            </div>
                            <div style="font-size: 10px; font-weight: 600; letter-spacing: 0.08em;
                                        text-transform: uppercase; color: var(--c-text-2); margin-top: 0.2rem;">
                                Total
                            </div>
                        </div>
                        <div class="col-4">
                            <div style="font-size: 1.6rem; font-weight: 700; line-height: 1;
                                        color: {{ $stock->quantite_disponible > 0 ? 'var(--c-success)' : 'var(--c-danger)' }};">
                                {{ $stock->quantite_disponible }}
                            </div>
                            <div style="font-size: 10px; font-weight: 600; letter-spacing: 0.08em;
                                        text-transform: uppercase; color: var(--c-text-2); margin-top: 0.2rem;">
                                Dispo
                            </div>
                        </div>
                        <div class="col-4">
                            <div style="font-size: 1.6rem; font-weight: 700; line-height: 1; color: var(--c-info);">
                                {{ $stock->quantite_louee }}
                            </div>
                            <div style="font-size: 10px; font-weight: 600; letter-spacing: 0.08em;
                                        text-transform: uppercase; color: var(--c-text-2); margin-top: 0.2rem;">
                                Loue
                            </div>
                        </div>
                    </div>

                    @if($stock->quantite_disponible > 0)
                        <div style="margin-bottom: 1rem; padding: 0.6rem 0.8rem; border-radius: var(--radius-sm);
                                    background: rgba(74,156,109,0.08); border: 1px solid rgba(74,156,109,0.18);
                                    font-size: 12px; color: var(--c-success); text-align: center;">
                            <i class="bi bi-check-circle me-1"></i>Disponible en stock
                        </div>
                    @else
                        <div style="margin-bottom: 1rem; padding: 0.6rem 0.8rem; border-radius: var(--radius-sm);
                                    background: rgba(192,72,72,0.08); border: 1px solid rgba(192,72,72,0.18);
                                    font-size: 12px; color: var(--c-danger); text-align: center;">
                            <i class="bi bi-x-circle me-1"></i>Aucun exemplaire disponible
                        </div>
                    @endif

                    <a href="{{ route('stocks.show', $filmId) }}" class="btn btn-outline-primary w-100 btn-sm">
                        <i class="bi bi-box-seam me-1"></i>Gerer le stock
                    </a>
                @else
                    <p style="color: var(--c-text-3); font-size: 13px; text-align: center; margin: 0.5rem 0;">
                        Stock non disponible pour ce film.
                    </p>
                @endif
            </div>
        </div>
    </div>

</div>

@endsection
