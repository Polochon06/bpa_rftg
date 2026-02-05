// Fonction pour le debouncing
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Fonction pour mettre à jour la table des films
function updateFilmsTable() {
    console.log('📊 updateFilmsTable() appelée');

    const searchInput = document.getElementById('searchInput');
    const yearFilter = document.getElementById('yearFilter');
    const ratingFilter = document.getElementById('ratingFilter');

    if (!searchInput || !yearFilter || !ratingFilter) {
        console.warn('⚠️ Filtres non trouvés dans updateFilmsTable');
        return;
    }

    const searchValue = searchInput.value.toLowerCase().trim();
    const yearValue = yearFilter.value.trim();
    const ratingValue = ratingFilter.value.trim();
    const rows = document.querySelectorAll('#filmsTableBody tr');

    console.log('🔍 Filtrage en cours:', { searchValue, yearValue, ratingValue, totalRows: rows.length });

    let visibleCount = 0;

    rows.forEach(row => {
        // Colonne 2 = Titre (avec le strong)
        const titleCell = row.querySelector('td:nth-child(2) strong');
        // Colonne 3 = Description (dans le div avec data-bs-toggle="tooltip")
        const descDiv = row.querySelector('td:nth-child(3) div[data-bs-toggle="tooltip"]');
        // Colonne 4 = Année
        const yearCell = row.querySelector('td:nth-child(4)');
        // Colonne 6 = Classification (dans le badge)
        const ratingBadge = row.querySelector('td:nth-child(6) .badge');

        if (!titleCell) {
            console.warn('Ligne sans titre trouvée');
            return;
        }

        const title = titleCell.textContent.toLowerCase().trim();
        // Utiliser le title (tooltip) qui contient la description complète
        const description = descDiv ? (descDiv.getAttribute('title') || descDiv.textContent).toLowerCase().trim() : '';
        const year = yearCell ? yearCell.textContent.trim() : '';
        const rating = ratingBadge ? ratingBadge.textContent.trim() : '';

        let show = true;

        // Filtre par titre ou description
        if (searchValue) {
            const matchTitle = title.includes(searchValue);
            const matchDesc = description.includes(searchValue);
            if (!matchTitle && !matchDesc) {
                show = false;
            }
        }

        // Filtre par année
        if (yearValue && year !== yearValue) {
            show = false;
        }

        // Filtre par rating (classification)
        if (ratingValue && rating !== ratingValue) {
            show = false;
        }

        row.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    });

    console.log('Résultats visibles:', visibleCount);

    // Mettre à jour le compteur de résultats
    const totalResults = document.getElementById('totalResults');
    if (totalResults) {
        totalResults.textContent = visibleCount;
    }

    // Afficher un message si aucun résultat
    const noResultsMessage = document.getElementById('noResultsMessage');
    if (noResultsMessage) {
        if (visibleCount === 0) {
            noResultsMessage.classList.remove('d-none');
        } else {
            noResultsMessage.classList.add('d-none');
        }
    }
}

// Fonction pour appliquer le filtre par catégorie (côté serveur)
function applyFilters() {
    console.log('🔄 Application des filtres côté serveur...');

    const categoryFilter = document.getElementById('categoryFilter');
    const categoryValue = categoryFilter ? categoryFilter.value : '';

    // Construire l'URL avec les paramètres
    const params = new URLSearchParams();
    if (categoryValue) {
        params.append('category', categoryValue);
    }

    // Rediriger vers la page avec les filtres
    const url = params.toString() ? `?${params.toString()}` : window.location.pathname;
    window.location.href = url;
}

// Fonction pour réinitialiser les filtres
function resetFilters() {
    const searchInput = document.getElementById('searchInput');
    const yearFilter = document.getElementById('yearFilter');
    const ratingFilter = document.getElementById('ratingFilter');
    const categoryFilter = document.getElementById('categoryFilter');

    if (searchInput) searchInput.value = '';
    if (yearFilter) yearFilter.value = '';
    if (ratingFilter) ratingFilter.value = '';
    if (categoryFilter) {
        categoryFilter.value = '';
        // Recharger la page sans paramètre pour réinitialiser le filtre catégorie
        window.location.href = window.location.pathname;
    } else {
        updateFilmsTable();
    }
}

// Fonction pour afficher les détails du film dans la modal
function showFilmDetails(filmData) {
    try {
        const film = typeof filmData === 'string' ? JSON.parse(filmData) : filmData;

        document.getElementById('modalFilmTitle').textContent = film.title || 'Sans titre';
        document.getElementById('modalFilmDescription').textContent = film.description || 'Aucune description';
        document.getElementById('modalFilmYear').textContent = film.releaseYear || 'N/A';
        document.getElementById('modalFilmLength').textContent = film.length ? `${film.length} min` : 'N/A';

        const ratingEl = document.getElementById('modalFilmRating');
        if (film.rating) {
            const badgeClass = {
                'G': 'bg-success',
                'PG': 'bg-info',
                'PG-13': 'bg-warning',
                'R': 'bg-danger'
            }[film.rating] || 'bg-secondary';
            ratingEl.innerHTML = `<span class="badge ${badgeClass}">${film.rating}</span>`;
        } else {
            ratingEl.textContent = 'N/A';
        }

        // Ouvrir la modal avec Bootstrap
        const modal = new bootstrap.Modal(document.getElementById('filmDetailsModal'));
        modal.show();
    } catch (error) {
        console.error('Erreur lors de l\'affichage des détails du film:', error);
    }
}

// Fonction pour supprimer un film
function deleteFilm(filmId, filmTitle) {
    if (!confirm(`Êtes-vous sûr de vouloir supprimer le film "${filmTitle}" ?`)) {
        return;
    }

    const form = document.getElementById(`delete-form-${filmId}`);
    if (form) {
        form.submit();
    }
}

// Initialisation des écouteurs d'événements
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎬 Films.js - DOMContentLoaded déclenché');

    const searchInput = document.getElementById('searchInput');
    const yearFilter = document.getElementById('yearFilter');
    const ratingFilter = document.getElementById('ratingFilter');
    const categoryFilter = document.getElementById('categoryFilter');
    const filterButton = document.getElementById('filterButton');
    const resetButtons = document.querySelectorAll('#resetFilters');

    console.log('🔍 Éléments trouvés:', {
        searchInput: searchInput ? 'OUI' : 'NON',
        yearFilter: yearFilter ? 'OUI' : 'NON',
        ratingFilter: ratingFilter ? 'OUI' : 'NON',
        categoryFilter: categoryFilter ? 'OUI' : 'NON',
        filterButton: filterButton ? 'OUI' : 'NON',
        resetButtons: resetButtons.length
    });

    // Filtres
    if (searchInput) {
        searchInput.addEventListener('input', debounce(updateFilmsTable, 300));
        console.log('✅ Event listener "input" attaché sur searchInput');
    } else {
        console.warn('⚠️ searchInput non trouvé !');
    }

    if (yearFilter) {
        yearFilter.addEventListener('change', updateFilmsTable);
        console.log('✅ Event listener "change" attaché sur yearFilter');
    } else {
        console.warn('⚠️ yearFilter non trouvé !');
    }

    if (ratingFilter) {
        ratingFilter.addEventListener('change', updateFilmsTable);
        console.log('✅ Event listener "change" attaché sur ratingFilter');
    } else {
        console.warn('⚠️ ratingFilter non trouvé !');
    }

    // Bouton de filtrage par catégorie
    if (filterButton) {
        filterButton.addEventListener('click', applyFilters);
        console.log('✅ Event listener "click" attaché sur filterButton');
    } else {
        console.warn('⚠️ filterButton non trouvé !');
    }

    // Boutons de réinitialisation
    resetButtons.forEach(button => {
        button.addEventListener('click', resetFilters);
    });

    // Boutons "Voir les détails"
    document.querySelectorAll('.view-film-details').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const filmData = this.getAttribute('data-film');
            showFilmDetails(filmData);
        });
    });

    // Boutons de suppression
    document.querySelectorAll('.delete-film').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const filmId = this.getAttribute('data-film-id');
            const filmTitle = this.getAttribute('data-film-title');
            deleteFilm(filmId, filmTitle);
        });
    });

    // Initialiser les tooltips Bootstrap
    try {
        if (typeof bootstrap !== 'undefined') {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                new bootstrap.Tooltip(tooltipTriggerEl);
            });
            console.log('✅ Tooltips initialisés:', tooltipTriggerList.length);
        } else {
            console.warn('⚠️ Bootstrap non disponible pour les tooltips');
        }
    } catch (error) {
        console.error('Erreur lors de l\'initialisation des tooltips:', error);
    }

    // Initialiser le compteur de résultats
    console.log('🔄 Initialisation du compteur de résultats...');
    updateFilmsTable();

    console.log('✅ Initialisation complète de films.js terminée');
});