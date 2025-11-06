@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Ajouter un nouveau film</h5>
                    <a href="{{ route('films.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Retour
                    </a>
                </div>

                <div class="card-body">
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('films.store') }}" method="POST" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label for="title" class="form-label">Titre</label>
                            <input id="title" name="title" type="text" class="form-control" value="{{ old('title') }}" required maxlength="255">
                            <div class="form-text">Ex: Le Grand Film (requis)</div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="4" required>{{ old('description') }}</textarea>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="releaseYear" class="form-label">Année de sortie</label>
                                <input id="releaseYear" name="releaseYear" type="number" class="form-control" value="{{ old('releaseYear', date('Y')) }}" min="1900" max="{{ date('Y') + 5 }}" required>
                            </div>

                            <div class="col-md-4">
                                <label for="length" class="form-label">Durée (min)</label>
                                <input id="length" name="length" type="number" class="form-control" value="{{ old('length') }}" min="1">
                            </div>

                            <div class="col-md-4">
                                <label for="rating" class="form-label">Note</label>
                                <select id="rating" name="rating" class="form-select">
                                    <option value="">--</option>
                                    @for($r = 0; $r <= 5; $r++)
                                        <option value="{{ $r }}" {{ (string)old('rating') === (string)$r ? 'selected' : '' }}>{{ $r }} {{ $r == 1 ? 'étoile' : 'étoiles' }}</option>
                                    @endfor
                                </select>
                            </div>
                        </div>

                        <div class="mt-4 d-flex justify-content-end gap-2">
                            <button type="reset" class="btn btn-outline-secondary">Réinitialiser</button>
                            <button type="submit" class="btn btn-primary">Enregistrer le film</button>
                        </div>
                    </form>
                </div>

                <div class="card-footer text-muted small">
                    Les champs marqués d'une étoile sont obligatoires.
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Petit contrôle client pour l'UX
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        const title = document.getElementById('title');
        const description = document.getElementById('description');
        if (!title.value.trim() || !description.value.trim()) {
            e.preventDefault();
            alert('Veuillez renseigner au moins le titre et la description du film.');
        }
    });
});
</script>
@endpush

@endsection