// Nettoyé: gestion des filtres, modal détails et suppression
// Dépendances : Bootstrap 5 pour les tooltips et modals

// Debounce helper
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func(...args), wait);
    };
}

// Debug: confirm script loaded
try {
    console.log('films.js loaded');
} catch (e) {}

// Update du compteur et message "aucun résultat"
function updateFilteredCount(count) {
    const totalResults = document.getElementById('totalResults');
    if (totalResults && typeof count === 'number') totalResults.textContent = count;

    const noResultsMessage = document.getElementById('noResultsMessage');
    if (noResultsMessage) {
        noResultsMessage.style.display = count === 0 ? 'block' : 'none';
    }
}

// Filtrage centralisé
function filterTable() {
    const searchInput = document.getElementById('searchInput');
    const yearFilter = document.getElementById('yearFilter');
    const ratingFilter = document.getElementById('ratingFilter');
    if (!document.getElementById('filmsTableBody')) return;

    const searchValue = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const yearValue = yearFilter ? yearFilter.value : '';
    const ratingValue = ratingFilter ? ratingFilter.value : '';
    const rows = document.querySelectorAll('#filmsTableBody tr');

    let visible = 0;
    const terms = searchValue.split(/\s+/).filter(s => s.length > 0);

    rows.forEach(row => {
        try {
            const title = (row.querySelector('td:nth-child(2)')?.textContent || '').toLowerCase();
            const desc = (row.querySelector('td:nth-child(3) .description-tooltip')?.getAttribute('title') || '').toLowerCase();
            const year = (row.querySelector('td:nth-child(4)')?.textContent || '').trim();
            const rating = (row.querySelector('td:nth-child(6) .badge')?.textContent || '').trim();

            const matchesSearch = terms.length === 0 || terms.every(t => title.includes(t) || desc.includes(t));
            const matchesYear = !yearValue || year === yearValue;
            const matchesRating = !ratingValue || rating === ratingValue;

            const show = matchesSearch && matchesYear && matchesRating;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        } catch (err) {
            console.error('Erreur filtre ligne:', err);
        }
    });

    updateFilteredCount(visible);
}

// Affiche les détails du film depuis un objet film
function showFilmDetailsFromObject(film) {
    if (!film) return;
    const titleEl = document.getElementById('modalFilmTitle');
    const descEl = document.getElementById('modalFilmDescription');
    const yearEl = document.getElementById('modalFilmYear');
    const lengthEl = document.getElementById('modalFilmLength');
    const ratingEl = document.getElementById('modalFilmRating');

    if (titleEl) titleEl.textContent = film.title || '—';
    if (descEl) descEl.textContent = film.description || 'Aucune description disponible';
    if (yearEl) yearEl.textContent = film.releaseYear || '--';
    if (lengthEl) lengthEl.textContent = film.length ? `${film.length} min` : '--';

    if (ratingEl) {
        if (film.rating) {
            const cls = film.rating === 'G' ? 'bg-success' : film.rating === 'PG' ? 'bg-info' : film.rating === 'PG-13' ? 'bg-warning' : film.rating === 'R' ? 'bg-danger' : 'bg-secondary';
            ratingEl.innerHTML = `<span class="badge ${cls}">${film.rating}</span>`;
        } else {
            ratingEl.textContent = '--';
        }
    }
}

// Suppression via formulaire caché
function handleDeleteFilm(filmId, filmTitle) {
    console.log('handleDeleteFilm called for', filmId, filmTitle);
    if (!filmId) {
        console.warn('handleDeleteFilm: filmId manquant');
        return;
    }
    if (!confirm(`Êtes-vous sûr de vouloir supprimer le film "${filmTitle || filmId}" ?`)) return;
    // Prefer AJAX delete for better UX
    try {
        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        const token = tokenMeta ? tokenMeta.getAttribute('content') : null;
        if (window.fetch && token) {
            const url = `/films/${encodeURIComponent(filmId)}`;
            console.log('Attempting AJAX DELETE to', url);
            fetch(url, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            }).then(resp => {
                if (!resp.ok) {
                    return resp.text().then(body => {
                        console.error('Delete response not ok', resp.status, body);
                        try {
                            const parsed = JSON.parse(body);
                            return Promise.reject(parsed);
                        } catch (e) {
                            return Promise.reject({ message: body || 'Erreur serveur', status: resp.status });
                        }
                    });
                }
                return resp.json().catch(() => ({}));
            }).then(data => {
                console.log('Suppression réussie', data);
                // Remove row from DOM
                const form = document.getElementById(`delete-form-${filmId}`);
                let tr = null;
                if (form) {
                    tr = form.closest('tr');
                } else {
                    // find by scanning first td values
                    const rows = document.querySelectorAll('#filmsTableBody tr');
                    rows.forEach(r => {
                        if (tr) return;
                        const idCell = r.querySelector('td:first-child');
                        if (idCell && idCell.textContent.trim() === String(filmId)) {
                            tr = r;
                        }
                    });
                }
                if (tr) tr.remove();
                // Update counts
                const remaining = document.querySelectorAll('#filmsTableBody tr:not([style*="display: none"])').length;
                updateFilteredCount(remaining);
                // Optionally show toast/alert
                alert(data.message || 'Film supprimé avec succès');
            }).catch(err => {
                console.error('Erreur lors de la suppression via AJAX:', err);
                // Fallback to form submit if exists
                const form = document.getElementById(`delete-form-${filmId}`);
                if (form) form.submit();
            });
            return;
        }
    } catch (e) {
        console.warn('AJAX delete non disponible, fallback au formulaire', e);
    }

    // Fallback: submit hidden form
    const form = document.getElementById(`delete-form-${filmId}`);
    if (!form) {
        console.error('Formulaire de suppression introuvable pour id', filmId);
        return;
    }
    console.log('Soumission du formulaire de suppression pour id', filmId);
    form.submit();
}

// Initialisation des écouteurs
document.addEventListener('DOMContentLoaded', function() {
    // Tooltips bootstrap (silencieux s'il n'existe pas)
    try {
        if (typeof bootstrap !== 'undefined') {
            const tt = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tt.forEach(el => new bootstrap.Tooltip(el));
        }
    } catch (e) { console.warn('Bootstrap tooltips init failed', e); }

    // Éléments
    const searchInput = document.getElementById('searchInput');
    const yearFilter = document.getElementById('yearFilter');
    const ratingFilter = document.getElementById('ratingFilter');

    // Listeners filtres
    if (searchInput) {
        searchInput.addEventListener('input', debounce(filterTable, 200));
        // Touche Enter déclenche la recherche (préviens si le champ est dans un formulaire)
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                filterTable();
            }
        });
    }

    if (yearFilter) yearFilter.addEventListener('change', filterTable);
    if (ratingFilter) ratingFilter.addEventListener('change', filterTable);

    // Boutons voir/modifier/supprimer
    document.querySelectorAll('.view-film-details').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            try {
                const raw = this.getAttribute('data-film');
                if (!raw) return;
                const film = JSON.parse(raw);
                showFilmDetailsFromObject(film);
                if (typeof bootstrap !== 'undefined') {
                    const modal = new bootstrap.Modal(document.getElementById('filmDetailsModal'));
                    modal.show();
                }
            } catch (err) {
                console.error('Impossible d\'ouvrir la modal détails:', err);
            }
        });
    });

    document.querySelectorAll('.delete-film').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-film-id');
            const title = this.getAttribute('data-film-title');
            console.log('delete button clicked', id, title);
            handleDeleteFilm(id, title);
        });
    });

    // Première initialisation
    filterTable();
});