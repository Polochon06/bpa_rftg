@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Détails du film</h5>
                    <a href="{{ route('films.index') }}" class="btn btn-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Retour à la liste
                    </a>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h3>{{ $film['title'] ?? 'Sans titre' }}</h3>
                            <p class="text-muted">{{ $film['description'] ?? 'Aucune description disponible.' }}</p>
                        </div>
                        <div class="col-md-4">
                            @if(isset($film['rating']))
                                <div class="text-end">
                                    <span class="badge bg-info fs-5">{{ $film['rating'] }}</span>
                                </div>
                            @endif

                            <!-- Encart Disponibilité Stock -->
                            <div class="card mt-3">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">📦 Disponibilité Stock</h6>
                                </div>
                                <div class="card-body">
                                    @if($stock && $stock->quantite_disponible > 0)
                                        <div class="mb-2">
                                            <span class="badge bg-success fs-6">{{ $stock->quantite_disponible }} DVD disponibles</span>
                                        </div>
                                        <form action="{{ route('stocks.louer', $film['filmId'] ?? $film['id']) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-primary w-100" onclick="return confirm('Confirmer la location de ce DVD ?')">
                                                Louer ce DVD
                                            </button>
                                        </form>
                                    @else
                                        <div class="alert alert-danger mb-0">
                                            <strong>🔴 Rupture de stock</strong>
                                        </div>
                                    @endif

                                    @if($stock && $stock->quantite_louee > 0)
                                        <div class="mt-2">
                                            <small class="text-muted">{{ $stock->quantite_louee }} DVD actuellement louées</small>
                                        </div>
                                    @endif

                                    <!-- Bouton Gérer le stock -->
                                    @if($stock)
                                        <div class="mt-3">
                                            <a href="{{ route('stocks.show', $film['filmId'] ?? $film['id']) }}" class="btn btn-success w-100">
                                                <i class="bi bi-box-seam"></i> Gérer le stock
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <dl class="row">
                        <dt class="col-sm-3">ID</dt>
                        <dd class="col-sm-9">{{ $film['filmId'] ?? $film['id'] ?? 'N/A' }}</dd>

                        <dt class="col-sm-3">Année de sortie</dt>
                        <dd class="col-sm-9">{{ $film['releaseYear'] ?? 'N/A' }}</dd>

                        <dt class="col-sm-3">Langue</dt>
                        <dd class="col-sm-9">ID {{ $film['languageId'] ?? 'N/A' }}</dd>

                        <dt class="col-sm-3">Durée</dt>
                        <dd class="col-sm-9">{{ $film['length'] ?? 'N/A' }} minutes</dd>

                        <dt class="col-sm-3">Coût de remplacement</dt>
                        <dd class="col-sm-9">{{ $film['replacementCost'] ?? 'N/A' }} €</dd>

                        <dt class="col-sm-3">Note</dt>
                        <dd class="col-sm-9">{{ $film['rating'] ?? 'N/A' }}</dd>

                        <dt class="col-sm-3">Caractéristiques spéciales</dt>
                        <dd class="col-sm-9">{{ $film['specialFeatures'] ?? 'Aucune' }}</dd>

                        <dt class="col-sm-3">Dernière mise à jour</dt>
                        <dd class="col-sm-9">{{ $film['lastUpdate'] ?? 'N/A' }}</dd>
                    </dl>

                    <hr>

                    <div class="d-flex gap-2">
                        <a href="#" class="btn btn-warning">
                            <i class="bi bi-pencil"></i> Modifier
                        </a>
                        <button class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce film ?')">
                            <i class="bi bi-trash"></i> Supprimer
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection