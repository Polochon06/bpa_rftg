<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Cache\TaggableStore;
use App\Models\Film as LocalFilm;
use Illuminate\Database\QueryException;

class ToadFilmService
{
    protected string $baseUrl;
    protected const TIMEOUT = 10;
    protected const RETRY_TIMES = 3;
    protected const RETRY_SLEEP = 100;

    public function __construct()
    {
        $toadUrl = config('services.toad.url');
        if (empty($toadUrl)) {
            Log::error('URL de l\'API Toad non configurée');
            throw new \RuntimeException('La configuration de l\'API Toad est manquante.');
        }
        $this->baseUrl = rtrim($toadUrl, '/');
    }

    protected function prepareRequest(): PendingRequest
    {
        // Priorité 1: Token de l'utilisateur connecté (session)
        $userToken = session('toad_user.token');

        // Priorité 2: Token de configuration (pour l'API générique)
        $configToken = config('services.toad.token');

        // Utiliser le token utilisateur si disponible, sinon le token de config
        $token = $userToken ?: $configToken;

        if (empty($token)) {
            Log::warning('Aucun token disponible (ni utilisateur ni config)');
        } else {
            Log::debug('Utilisation du token', [
                'type' => $userToken ? 'utilisateur' : 'config',
                'token_preview' => substr($token, 0, 20) . '...'
            ]);
        }

        $request = Http::timeout(self::TIMEOUT)
            ->retry(self::RETRY_TIMES, self::RETRY_SLEEP)
            ->acceptJson()
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ]);

        if ($token) {
            $request = $request->withToken($token);
        }

        return $request;
    }

    protected function getNextFilmId(): int
    {
        try {
            $maxId = 1000; // ID de départ

            // Vérifier les IDs de l'API
            try {
                $remoteFilms = $this->getAllFilms() ?? [];
                foreach ($remoteFilms as $film) {
                    $currentId = (int)($film['id'] ?? 0);
                    if ($currentId > $maxId) {
                        $maxId = $currentId;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Impossible de récupérer les films distants pour l\'ID', ['error' => $e->getMessage()]);
            }

            // Vérifier les IDs locaux
            try {
                $localMaxId = LocalFilm::max('id') ?? 0;
                if ($localMaxId > $maxId) {
                    $maxId = $localMaxId;
                }
            } catch (QueryException $e) {
                Log::warning('Base de données locale non disponible pour l\'ID', ['error' => $e->getMessage()]);
            }

            return $maxId + 1;
        } catch (\Exception $e) {
            Log::error('Erreur lors de la génération du prochain ID', ['error' => $e->getMessage()]);
            return time(); // Fallback à un timestamp comme ID
        }
    }

    public function createFilm(array $data): ?array
    {
        try {
            $nextId = $this->getNextFilmId();
            
            $filmData = [
                'id' => $nextId,
                'title' => $data['title'],
                'description' => $data['description'],
                'releaseYear' => (int) $data['releaseYear'],
                'length' => (int) $data['length'],
                'rating' => $data['rating']
            ];

            // Essayer d'abord l'API distante
            try {
                $response = $this->prepareRequest()->post($this->baseUrl . '/films', $filmData);
                if ($response->successful()) {
                    $this->flushFilmsCache();
                    Log::info('Film créé avec succès via l\'API', ['id' => $nextId]);
                    return $response->json();
                }
            } catch (\Exception $e) {
                Log::warning('API distante non disponible pour la création', ['error' => $e->getMessage()]);
            }

            // Si l'API échoue, essayer la base de données locale
            try {
                $localFilm = LocalFilm::create([
                    'id' => $nextId,
                    'filmId' => 'local_' . $nextId,
                    'title' => $filmData['title'],
                    'description' => $filmData['description'],
                    'releaseYear' => $filmData['releaseYear'],
                    'length' => $filmData['length'],
                    'rating' => $filmData['rating']
                ]);

                $this->flushFilmsCache();
                Log::info('Film créé localement avec succès', ['id' => $nextId]);
                
                return [
                    'id' => $localFilm->id,
                    'filmId' => $localFilm->filmId,
                    'title' => $localFilm->title,
                    'description' => $localFilm->description,
                    'releaseYear' => $localFilm->releaseYear,
                    'length' => $localFilm->length,
                    'rating' => $localFilm->rating
                ];
            } catch (QueryException $e) {
                Log::warning('Base de données locale non disponible pour la création', ['error' => $e->getMessage()]);
            }

            // En dernier recours, sauvegarder dans le fichier
            $fallbackPath = storage_path('app/films_fallback.json');
            try {
                $films = [];
                if (file_exists($fallbackPath)) {
                    $content = file_get_contents($fallbackPath);
                    $films = json_decode($content, true) ?: [];
                }

                $newFilm = array_merge($filmData, ['filmId' => 'local_' . $nextId]);
                $films[] = $newFilm;

                file_put_contents($fallbackPath, json_encode($films, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $this->flushFilmsCache();
                
                Log::info('Film créé dans le fichier de sauvegarde', ['id' => $nextId]);
                return $newFilm;
            } catch (\Exception $e) {
                Log::error('Échec de la création du film dans le fichier', ['error' => $e->getMessage()]);
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Erreur lors de la création du film', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Normalise les données d'un film de snake_case vers camelCase
     */
    protected function normalizeFilm(array $film): array
    {
        return [
            'filmId' => $film['film_id'] ?? $film['filmId'] ?? $film['id'] ?? null,
            'id' => $film['film_id'] ?? $film['id'] ?? null,
            'title' => $film['title'] ?? null,
            'description' => $film['description'] ?? null,
            'releaseYear' => $film['release_year'] ?? $film['releaseYear'] ?? null,
            'languageId' => $film['language_id'] ?? $film['languageId'] ?? null,
            'rentalDuration' => $film['rental_duration'] ?? $film['rentalDuration'] ?? null,
            'rentalRate' => $film['rental_rate'] ?? $film['rentalRate'] ?? null,
            'length' => $film['length'] ?? null,
            'replacementCost' => $film['replacement_cost'] ?? $film['replacementCost'] ?? null,
            'rating' => $film['rating'] ?? null,
            'specialFeatures' => $film['special_features'] ?? $film['specialFeatures'] ?? null,
            'lastUpdate' => $film['last_update'] ?? $film['lastUpdate'] ?? null,
        ];
    }

    public function getAllFilms(): ?array
    {
        try {
            $response = $this->prepareRequest()->get($this->baseUrl . '/films');

            if ($response->successful()) {
                $remote = $response->json() ?: [];

                // Normaliser les films distants (snake_case vers camelCase)
                $remote = array_map([$this, 'normalizeFilm'], $remote);

                try {
                    $local = LocalFilm::all()->map(function($f) {
                        return [
                            'filmId' => $f->filmId ?? null,
                            'id' => $f->id,
                            'title' => $f->title,
                            'description' => $f->description,
                            'releaseYear' => $f->releaseYear,
                            'length' => $f->length,
                            'rating' => $f->rating,
                        ];
                    })->toArray();

                    if (!empty($local)) {
                        $existing = [];
                        foreach ($remote as $r) {
                            if (isset($r['filmId'])) $existing[] = (string)$r['filmId'];
                            if (isset($r['id'])) $existing[] = (string)$r['id'];
                        }

                        foreach ($local as $l) {
                            $lid = (string)($l['filmId'] ?? $l['id'] ?? '');
                            if ($lid === '') continue;
                            if (!in_array($lid, $existing, true)) {
                                $remote[] = $l;
                            }
                        }
                    }
                } catch (QueryException $qe) {
                    // DB not available - pas de souci, on continue
                }

                // Toujours charger les films du fichier fallback (films modifiés ou créés localement)
                $fallbackPath = storage_path('app/films_fallback.json');
                if (file_exists($fallbackPath)) {
                    $content = file_get_contents($fallbackPath);
                    $fallbackFilms = json_decode($content, true) ?: [];

                    // Construire une liste des IDs déjà présents
                    $existingIds = [];
                    foreach ($remote as $r) {
                        if (isset($r['filmId'])) $existingIds[] = (string)$r['filmId'];
                        if (isset($r['id'])) $existingIds[] = (string)$r['id'];
                    }

                    // Ajouter ou remplacer les films du fallback
                    foreach ($fallbackFilms as $fallbackFilm) {
                        $fid = (string)($fallbackFilm['filmId'] ?? $fallbackFilm['id'] ?? '');
                        if ($fid === '') continue;

                        // Si c'est un film modifié (modified_X), remplacer l'original
                        if (isset($fallbackFilm['originalId'])) {
                            $originalId = (string)$fallbackFilm['originalId'];
                            // Trouver et remplacer le film original
                            foreach ($remote as $key => $film) {
                                $filmIdToCheck = (string)($film['filmId'] ?? $film['id'] ?? '');
                                if ($filmIdToCheck === $originalId) {
                                    // Remplacer le film original par la version modifiée
                                    $remote[$key] = $fallbackFilm;
                                    Log::debug('Film distant remplacé par version modifiée', ['originalId' => $originalId, 'modifiedId' => $fid]);
                                    continue 2; // Passer au film suivant
                                }
                            }
                        }

                        // Sinon, ajouter le film s'il n'existe pas déjà
                        if (!in_array($fid, $existingIds, true)) {
                            $remote[] = $fallbackFilm;
                            Log::debug('Film du fallback ajouté', ['id' => $fid]);
                        }
                    }
                }

                return $remote;
            }
            
            Log::error('Erreur lors de la récupération des films depuis l\'API', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            
            // Si l'API échoue, essayer de retourner uniquement les films locaux
            try {
                $local = LocalFilm::all()->map(function($f) {
                    return [
                        'filmId' => $f->filmId ?? null,
                        'id' => $f->id,
                        'title' => $f->title,
                        'description' => $f->description,
                        'releaseYear' => $f->releaseYear,
                        'length' => $f->length,
                        'rating' => $f->rating,
                    ];
                })->toArray();
                
                if (!empty($local)) {
                    Log::info('Retour des films locaux uniquement (API indisponible)');
                    return $local;
                }
            } catch (QueryException $qe) {
                // Si la BD n'est pas disponible, essayer le fichier
                $fallbackPath = storage_path('app/films_fallback.json');
                if (file_exists($fallbackPath)) {
                    $content = file_get_contents($fallbackPath);
                    $local = json_decode($content, true);
                    if (!empty($local)) {
                        Log::info('Retour des films du fichier de sauvegarde (API et DB indisponibles)');
                        return $local;
                    }
                }
            }

            return [];

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des films', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function getFilmById($id): ?array
    {
        try {
            Log::info('Recherche du film', ['id' => $id]);

            // D'abord vérifier le fichier fallback pour les films modifiés (priorité haute)
            $fallbackPath = storage_path('app/films_fallback.json');
            if (file_exists($fallbackPath)) {
                try {
                    $content = file_get_contents($fallbackPath);
                    $films = json_decode($content, true) ?: [];

                    foreach ($films as $film) {
                        // Vérifier si c'est le film qu'on cherche ou s'il remplace l'original
                        if ((isset($film['filmId']) && $film['filmId'] == $id) ||
                            (isset($film['id']) && $film['id'] == $id) ||
                            (isset($film['originalId']) && $film['originalId'] == $id)) {
                            Log::info('Film trouvé dans le fichier de sauvegarde (version modifiée)', ['id' => $id]);
                            return $film;
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Erreur lors de la lecture du fichier', ['error' => $e->getMessage()]);
                }
            }

            // Si c'est un film local, chercher dans la base de données locale
            if (is_string($id) && str_starts_with($id, 'local_')) {
                try {
                    $localFilm = LocalFilm::where('filmId', $id)
                        ->orWhere('id', str_replace('local_', '', $id))
                        ->first();

                    if ($localFilm) {
                        $film = [
                            'filmId' => $localFilm->filmId,
                            'id' => $localFilm->id,
                            'title' => $localFilm->title,
                            'description' => $localFilm->description,
                            'releaseYear' => $localFilm->releaseYear,
                            'length' => $localFilm->length,
                            'rating' => $localFilm->rating
                        ];
                        Log::info('Film trouvé dans la base locale', ['id' => $id]);
                        return $film;
                    }
                } catch (QueryException $e) {
                    Log::warning('Base de données locale non disponible', ['error' => $e->getMessage()]);
                }
            }

            // Essayer l'API pour les films non locaux
            if (!str_starts_with($id, 'local_')) {
                try {
                    $response = $this->prepareRequest()->get($this->baseUrl . '/films/' . $id);
                    if ($response->successful()) {
                        $film = $response->json();
                        // Normaliser le film de l'API (snake_case vers camelCase)
                        $film = $this->normalizeFilm($film);
                        Log::info('Film trouvé via l\'API', ['id' => $id]);
                        return $film;
                    }
                } catch (\Exception $e) {
                    Log::warning('API non disponible', ['error' => $e->getMessage()]);
                }
            }

            Log::warning('Film non trouvé', ['id' => $id]);
            return null;

        } catch (\Exception $e) {
            Log::error('Erreur lors de la recherche du film', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function updateFilm($id, array $data): ?array
    {
        try {
            Log::info('Tentative de mise à jour du film', ['id' => $id]);
            
            $filmData = [
                'id' => $id, // Conserver l'ID original
                'title' => $data['title'],
                'description' => $data['description'],
                'releaseYear' => (int) $data['releaseYear'],
                'length' => (int) $data['length'],
                'rating' => $data['rating']
            ];

            // Essayer d'abord la base de données locale
            try {
                $searchId = (string)$id;
                $searchIdNoPrefix = str_replace('local_', '', $searchId);
                
                $lf = LocalFilm::where('filmId', $searchId)
                    ->orWhere('filmId', $searchIdNoPrefix)
                    ->orWhere('id', $searchId)
                    ->orWhere('id', $searchIdNoPrefix)
                    ->first();

                if ($lf) {
                    Log::info('Mise à jour du film dans la base de données locale', ['id' => $id]);
                    $lf->update($filmData);
                    $this->flushFilmsCache();
                    
                    $updatedFilm = [
                        'filmId' => $lf->filmId,
                        'id' => $lf->id,
                        'title' => $lf->title,
                        'description' => $lf->description,
                        'releaseYear' => $lf->releaseYear,
                        'length' => $lf->length,
                        'rating' => $lf->rating
                    ];
                    
                    Log::info('Film local mis à jour avec succès', ['film' => $updatedFilm]);
                    return $updatedFilm;
                }
            } catch (QueryException $qe) {
                Log::warning('Base de données locale non disponible', [
                    'error' => $qe->getMessage()
                ]);
            }

            // Si ce n'est pas un film local, l'API TOAD est en lecture seule
            // Solution: Copier le film dans le stockage local/fichier pour le modifier
            if (!str_starts_with($id, 'local_')) {
                Log::info('Film distant détecté - Copie vers le stockage local pour modification', [
                    'id' => $id
                ]);

                // L'API TOAD/Peach ne permet pas la modification des films existants (403 Forbidden)
                // On copie donc le film dans notre système de fichiers local
            }

            // Utiliser le fichier de sauvegarde comme stockage modifiable
            $fallbackPath = storage_path('app/films_fallback.json');

            // S'assurer que le fichier existe
            if (!file_exists($fallbackPath)) {
                file_put_contents($fallbackPath, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            $content = file_get_contents($fallbackPath);
            $films = json_decode($content, true) ?: [];

            $updated = false;
            foreach ($films as $key => $film) {
                if ((isset($film['filmId']) && $film['filmId'] == $id) ||
                    (isset($film['id']) && $film['id'] == $id)) {
                    // Film déjà dans le fichier, le mettre à jour
                    $films[$key] = array_merge($film, $filmData, ['filmId' => $film['filmId'] ?? $id]);
                    $updated = true;
                    break;
                }
            }

            // Si le film n'est pas dans le fichier, c'est un film distant qu'on copie
            if (!$updated) {
                Log::info('Ajout du film distant au stockage local pour modification', ['id' => $id]);
                $newFilm = array_merge($filmData, [
                    'id' => $id,
                    'filmId' => $id,
                    'originalId' => $id
                ]);
                $films[] = $newFilm;
                $updated = true;
            }

            if ($updated) {
                file_put_contents($fallbackPath, json_encode($films, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $this->flushFilmsCache();
                Log::info('Film mis à jour/copié avec succès dans le fichier de sauvegarde', ['id' => $id]);
                return array_merge(['id' => $id, 'filmId' => $id], $filmData);
            }

            Log::error('Échec inattendu de la mise à jour', ['id' => $id]);
            return null;
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour du film', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    protected function flushFilmsCache(): void
    {
        try {
            if (Cache::getStore() instanceof TaggableStore) {
                Cache::tags(['films'])->flush();
            }
        } catch (\Exception $e) {
            Log::warning('Impossible de nettoyer le cache avec tags', [
                'error' => $e->getMessage()
            ]);
        }
    }

    public function deleteFilm($id): bool
    {
        try {
            Log::info('Tentative de suppression du film', ['id' => $id]);

            // Si c'est un film local, essayer d'abord la base de données locale
            $isLocal = is_string($id) && str_starts_with($id, 'local_');
            if ($isLocal) {
                try {
                    $localFilm = LocalFilm::where('filmId', $id)
                        ->orWhere('id', str_replace('local_', '', $id))
                        ->first();

                    if ($localFilm) {
                        $localFilm->delete();
                        $this->flushFilmsCache();
                        Log::info('Film supprimé de la base locale', ['id' => $id]);
                        return true;
                    }
                } catch (QueryException $e) {
                    Log::warning('Base de données locale non disponible', ['error' => $e->getMessage()]);
                }
            }

            // Si ce n'est pas un film local ou la BD n'est pas disponible, essayer l'API
            if (!$isLocal) {
                try {
                    $response = $this->prepareRequest()->delete($this->baseUrl . '/films/' . $id);
                    if ($response->successful()) {
                        $this->flushFilmsCache();
                        Log::info('Film supprimé via l\'API', ['id' => $id]);
                        return true;
                    }
                } catch (\Exception $e) {
                    Log::warning('API non disponible pour la suppression', ['error' => $e->getMessage()]);
                }
            }

            // En dernier recours, essayer le fichier de sauvegarde
            $fallbackPath = storage_path('app/films_fallback.json');
            if (file_exists($fallbackPath)) {
                try {
                    $content = file_get_contents($fallbackPath);
                    $films = json_decode($content, true) ?: [];
                    
                    $newFilms = array_filter($films, function($film) use ($id) {
                        return !((isset($film['filmId']) && $film['filmId'] == $id) ||
                               (isset($film['id']) && $film['id'] == $id) ||
                               (isset($film['originalId']) && $film['originalId'] == $id));
                    });
                    
                    if (count($newFilms) < count($films)) {
                        file_put_contents($fallbackPath, json_encode(array_values($newFilms), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                        $this->flushFilmsCache();
                        Log::info('Film supprimé du fichier de sauvegarde', ['id' => $id]);
                        return true;
                    }
                } catch (\Exception $e) {
                    Log::error('Erreur lors de la suppression dans le fichier', ['error' => $e->getMessage()]);
                }
            }

            Log::warning('Film non trouvé pour la suppression', ['id' => $id]);
            return false;

        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression du film', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}