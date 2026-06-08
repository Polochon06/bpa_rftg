@extends('layouts.app')

@section('content')

<a href="{{ route('films.index') }}" class="back-link">
    <i class="bi bi-arrow-left"></i> Retour au catalogue
</a>

<div class="section-header">
    <div>
        <h1 class="section-title">Modifier un film</h1>
        <p class="section-sub">{{ $film['title'] ?? '' }}</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body" style="padding: 2rem !important;">

                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show mb-4">
                        <strong>Erreurs :</strong>
                        <ul style="margin: 0.5rem 0 0; padding-left: 1.2rem;">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <form action="{{ route('films.update', $film['filmId'] ?? $film['id']) }}"
                      method="POST" novalidate>
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label for="title" class="form-label">Titre *</label>
                        <input id="title" name="title" type="text"
                               class="form-control @error('title') is-invalid @enderror"
                               value="{{ old('title', $film['title']) }}" required maxlength="255">
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-4">
                        <label for="description" class="form-label">Description *</label>
                        <textarea id="description" name="description" rows="4" required
                                  class="form-control @error('description') is-invalid @enderror">{{ old('description', $film['description']) }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="releaseYear" class="form-label">Annee *</label>
                            <input id="releaseYear" name="releaseYear" type="number"
                                   class="form-control @error('releaseYear') is-invalid @enderror"
                                   value="{{ old('releaseYear', $film['releaseYear']) }}"
                                   min="1900" max="{{ date('Y') + 5 }}" required>
                            @error('releaseYear')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="length" class="form-label">Duree (min) *</label>
                            <input id="length" name="length" type="number"
                                   class="form-control @error('length') is-invalid @enderror"
                                   value="{{ old('length', $film['length']) }}" min="1" required>
                            @error('length')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="rating" class="form-label">Classification *</label>
                            <select id="rating" name="rating"
                                    class="form-select @error('rating') is-invalid @enderror" required>
                                <option value="" disabled>Choisir...</option>
                                <option value="G"     {{ old('rating', $film['rating']) == 'G'     ? 'selected' : '' }}>G - Tout public</option>
                                <option value="PG"    {{ old('rating', $film['rating']) == 'PG'    ? 'selected' : '' }}>PG</option>
                                <option value="PG-13" {{ old('rating', $film['rating']) == 'PG-13' ? 'selected' : '' }}>PG-13</option>
                                <option value="R"     {{ old('rating', $film['rating']) == 'R'     ? 'selected' : '' }}>R - Restreint</option>
                            </select>
                            @error('rating')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-secondary" onclick="history.back()">
                            Annuler
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check me-1"></i>Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
const form = document.querySelector('form');
form.addEventListener('submit', function (e) {
    if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    }
    form.classList.add('was-validated');
});
</script>
@endpush

@endsection
