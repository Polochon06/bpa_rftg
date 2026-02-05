@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-film me-2"></i>
                        Modifier un film
                    </h5>
                    <a href="{{ route('films.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Retour à la liste
                    </a>
                </div>

                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>Oups !</strong> Veuillez corriger les erreurs suivantes :
                            <ul class="mb-0 mt-2">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                        </div>
                    @endif

                    <form action="{{ route('films.update', $film['filmId'] ?? $film['id']) }}" method="POST" novalidate>
                        @csrf
                        @method('PUT')

                        <div class="mb-4">
                            <label for="title" class="form-label">Titre *</label>
                            <input id="title" name="title" type="text" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $film['title']) }}" required maxlength="255">
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Ex: Le Grand Film</div>
                        </div>

                        <div class="mb-4">
                            <label for="description" class="form-label">Description *</label>
                            <textarea id="description" name="description" class="form-control @error('description') is-invalid @enderror" rows="4" required>{{ old('description', $film['description']) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-4">
                                <label for="releaseYear" class="form-label">Année de sortie *</label>
                                <input id="releaseYear" name="releaseYear" type="number" class="form-control @error('releaseYear') is-invalid @enderror" value="{{ old('releaseYear', $film['releaseYear']) }}" min="1900" max="{{ date('Y') + 5 }}" required>
                                @error('releaseYear')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="length" class="form-label">Durée (min) *</label>
                                <input id="length" name="length" type="number" class="form-control @error('length') is-invalid @enderror" value="{{ old('length', $film['length']) }}" min="1" required>
                                @error('length')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="rating" class="form-label">Classification *</label>
                                <select id="rating" name="rating" class="form-select @error('rating') is-invalid @enderror" required>
                                    <option value="" disabled>Choisir...</option>
                                    <option value="G" {{ old('rating', $film['rating']) == 'G' ? 'selected' : '' }}>G (Tout public)</option>
                                    <option value="PG" {{ old('rating', $film['rating']) == 'PG' ? 'selected' : '' }}>PG (Accord parental souhaitable)</option>
                                    <option value="PG-13" {{ old('rating', $film['rating']) == 'PG-13' ? 'selected' : '' }}>PG-13 (Accord parental nécessaire)</option>
                                    <option value="R" {{ old('rating', $film['rating']) == 'R' ? 'selected' : '' }}>R (Restreint)</option>
                                </select>
                                @error('rating')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-danger" onclick="history.back()">
                                <i class="bi bi-x-lg me-2"></i>Annuler
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-2"></i>Enregistrer les modifications
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Validation côté client
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        form.classList.add('was-validated');
    });
</script>
@endpush
@endsection