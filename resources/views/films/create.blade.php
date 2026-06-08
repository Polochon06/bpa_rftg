@extends('layouts.app')

@section('content')

<a href="{{ route('films.index') }}" class="back-link">
    <i class="bi bi-arrow-left"></i> Retour au catalogue
</a>

<div class="section-header">
    <h1 class="section-title">Ajouter un film</h1>
</div>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body" style="padding: 2rem !important;">

                @if(session('error'))
                    <div class="alert alert-danger mb-4">{{ session('error') }}</div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger mb-4">
                        <ul style="margin: 0; padding-left: 1.2rem;">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('films.store') }}" method="POST" novalidate>
                    @csrf

                    <div class="mb-4">
                        <label for="title" class="form-label">Titre *</label>
                        <input id="title" name="title" type="text"
                               class="form-control @error('title') is-invalid @enderror"
                               value="{{ old('title') }}" required maxlength="255"
                               placeholder="Ex : Le Grand Film">
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-4">
                        <label for="description" class="form-label">Description *</label>
                        <textarea id="description" name="description" rows="4" required
                                  class="form-control @error('description') is-invalid @enderror"
                                  placeholder="Synopsis du film...">{{ old('description') }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="releaseYear" class="form-label">Annee *</label>
                            <input id="releaseYear" name="releaseYear" type="number"
                                   class="form-control @error('releaseYear') is-invalid @enderror"
                                   value="{{ old('releaseYear', date('Y')) }}"
                                   min="1900" max="{{ date('Y') + 5 }}" required>
                            @error('releaseYear')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="length" class="form-label">Duree (min) *</label>
                            <input id="length" name="length" type="number"
                                   class="form-control @error('length') is-invalid @enderror"
                                   value="{{ old('length') }}" min="1" required placeholder="90">
                            @error('length')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="rating" class="form-label">Classification *</label>
                            <select id="rating" name="rating"
                                    class="form-select @error('rating') is-invalid @enderror" required>
                                <option value="">Choisir...</option>
                                <option value="G"     {{ old('rating') === 'G'     ? 'selected' : '' }}>G - Tout public</option>
                                <option value="PG"    {{ old('rating') === 'PG'    ? 'selected' : '' }}>PG</option>
                                <option value="PG-13" {{ old('rating') === 'PG-13' ? 'selected' : '' }}>PG-13</option>
                                <option value="R"     {{ old('rating') === 'R'     ? 'selected' : '' }}>R - Restreint</option>
                            </select>
                            @error('rating')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="reset" class="btn btn-outline-secondary">Reinitialiser</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check me-1"></i>Enregistrer le film
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-footer">
                Les champs marques d'une * sont obligatoires.
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelector('form').addEventListener('submit', function (e) {
        const title = document.getElementById('title');
        const description = document.getElementById('description');
        if (!title.value.trim() || !description.value.trim()) {
            e.preventDefault();
            alert('Veuillez renseigner au moins le titre et la description.');
        }
    });
});
</script>
@endpush

@endsection
