<?php

/**
 * SERVICE FILMS (ToadFilmService)
 *
 * Rôle : point d'accès unique à toutes les opérations sur les films.
 *
 * Architecture "double source" :
 *  - LECTURE   → API Toad (base Peach distante) en priorité
 *  - ÉCRITURE  → API Toad en priorité (POST/DELETE exposés par le contrôleur Java)
 *               Fallback SQLite local si l'API est indisponible ou retourne une erreur
 *
 * Fusion locale/distante :
 *  Si un film local a le même filmId qu'un film distant,
 *  le film LOCAL remplace (override) le film distant dans la liste.
 *  Cela permet de modifier un film Peach sans toucher à l'API.
 */

namespace App\Services;

use App\Models\Film as LocalFilm;        // Modèle Eloquent → table films (SQLite local)
use Illuminate\Support\Facades\Http;    // Client HTTP de Laravel (wrapping Guzzle)
use Illuminate\Support\Facades\Log;     // Journalisation

class ToadFilmService
{
    // URL de base de l'API Toad (ex: http://localhost:3001/api)
    protected string $baseUrl;

    /**
     * Constructeur : lit l'URL de l'API depuis la config (services.toad.url).
     * Cette valeur est définie dans config/services.php et lue depuis .env (TOAD_API_URL).
     */
    public function __construct()
    {
        $url = config('services.toad.url');

        // Sécurité : impossible de fonctionner sans URL configurée
        if (empty($url)) {
            throw new \RuntimeException('TOAD_API_URL non configuré.');
        }

        // rtrim supprime le '/' final pour éviter les doubles slashes dans les URLs
        $this->baseUrl = rtrim($url, '/');
    }

    // =========================================================================
    // CLIENT HTTP INTERNE
    // =========================================================================

    /**
     * Crée et configure le client HTTP pour chaque appel à l'API.
     *
     * Token résolu dans cet ordre :
     *  1. Token JWT de l'utilisateur connecté (en session après login)
     *  2. Token de service défini dans config/services.php (accès anonyme)
     */
    private function http()
    {
        $token = session('toad_user.token') ?: config('services.toad.token');

        // acceptJson() : ajoute le header "Accept: application/json"
        // timeout(10)  : abandonne la requête après 10 secondes (évite les blocages)
        $client = Http::acceptJson()->timeout(10);

        // withToken() ajoute "Authorization: Bearer {token}" à chaque requête
        if ($token) {
            $client = $client->withToken($token);
        }

        return $client;
    }

    // =========================================================================
    // NORMALISATION DES DONNÉES
    // =========================================================================

    /**
     * Transforme un film distant (réponse API Peach) en tableau unifié.
     *
     * L'API Peach utilise le camelCase (filmId, releaseYear…).
     * On garde exactement ces noms pour rester cohérent avec la vue.
     * 'source' = 'remote' permet à la vue de savoir d'où vient le film.
     */
    private function normalizeRemote(array $film): array
    {
        return [
            'id'              => $film['filmId'] ?? null,      // id = alias de filmId
            'filmId'          => $film['filmId'] ?? null,
            'title'           => $film['title'] ?? null,
            'description'     => $film['description'] ?? null,
            'releaseYear'     => $film['releaseYear'] ?? null,
            'length'          => $film['length'] ?? null,
            'rating'          => $film['rating'] ?? null,
            'languageId'      => $film['originalLanguageId'] ?? null,
            'rentalDuration'  => $film['rentalDuration'] ?? null,
            'rentalRate'      => $film['rentalRate'] ?? null,
            'replacementCost' => $film['replacementCost'] ?? null,
            'specialFeatures' => $film['specialFeatures'] ?? null,
            'lastUpdate'      => $film['lastUpdate'] ?? null,
            'source'          => 'remote', // Marqueur : vient de l'API Peach
        ];
    }

    /**
     * Transforme un film local (modèle Eloquent) en tableau unifié.
     *
     * Si le film n'a pas de filmId Peach (film créé en local),
     * on génère un ID fictif "local_{id}" pour éviter toute collision.
     * 'source' = 'local' permet à la vue de savoir d'où vient le film.
     */
    private function normalizeLocal(LocalFilm $f): array
    {
        return [
            'id'          => $f->filmId ?? 'local_' . $f->id,
            'filmId'      => $f->filmId ?? 'local_' . $f->id,
            'title'       => $f->title,
            'description' => $f->description,
            'releaseYear' => $f->releaseYear,
            'length'      => $f->length,
            'rating'      => $f->rating,
            'source'      => 'local',        // Marqueur : film stocké en SQLite
            '_localId'    => $f->id,         // ID SQLite brut (utile pour le debug)
        ];
    }

    // =========================================================================
    // LECTURE
    // =========================================================================

    /**
     * Retourne la liste complète des films (distants + locaux fusionnés).
     *
     * Algorithme de fusion :
     *  1. Récupérer tous les films distants et les indexer par filmId
     *  2. Pour chaque film local, l'ajouter ou remplacer l'entrée distante si même filmId
     *  3. Retourner le tableau final réindexé
     */
    public function getAllFilms(): array
    {
        // --- Étape 1 : Films distants depuis l'API ---
        $remote = [];
        try {
            $resp = $this->http()->get($this->baseUrl . '/films');

            if ($resp->successful()) {
                // array_map applique normalizeRemote() à chaque film de la réponse JSON
                $remote = array_map([$this, 'normalizeRemote'], $resp->json() ?: []);
            } else {
                Log::warning('getAllFilms: API status ' . $resp->status());
            }
        } catch (\Exception $e) {
            // Si l'API est hors ligne, on continue avec uniquement les films locaux
            Log::error('getAllFilms: ' . $e->getMessage());
        }

        // Indexer les films distants par filmId (string) pour permettre l'override
        $indexed = [];
        foreach ($remote as $f) {
            $indexed[(string)($f['filmId'])] = $f;
        }

        // --- Étape 2 : Films locaux (override ou ajout) ---
        foreach (LocalFilm::all() as $local) {
            $norm = $this->normalizeLocal($local);
            $key  = (string)($local->filmId ?? 'local_' . $local->id);

            // Si la clé existe déjà (film Peach), le film local REMPLACE le film distant
            $indexed[$key] = $norm;
        }

        // array_values() : réindexe de 0 à N (array associatif → tableau numéroté)
        return array_values($indexed);
    }

    /**
     * Retourne un film par son ID.
     *
     * Priorité au local : si un override existe pour ce filmId, il est retourné en premier.
     * Sinon, appel à l'API Toad.
     */
    public function getFilmById($id): ?array
    {
        $idStr = (string) $id;

        // Chercher d'abord en base SQLite (override local ou film 100% local)
        $local = LocalFilm::where('filmId', $idStr)
            ->orWhere('id', is_numeric($id) ? (int)$id : null) // Cherche aussi par id SQLite
            ->first();

        if ($local) {
            return $this->normalizeLocal($local); // Local trouvé → retourner immédiatement
        }

        // Sinon interroger l'API Toad
        try {
            $resp = $this->http()->get($this->baseUrl . '/films/' . $id);

            if ($resp->successful()) {
                return $this->normalizeRemote($resp->json());
            }

            Log::warning("getFilmById($id): status " . $resp->status());
        } catch (\Exception $e) {
            Log::error("getFilmById($id): " . $e->getMessage());
        }

        return null; // Film introuvable ni en local ni en distant
    }

    // =========================================================================
    // MUTATIONS (toutes stockées en local car l'API Peach est en lecture seule)
    // =========================================================================

    /**
     * Crée un film via l'API Toad (POST /films).
     * Fallback : si l'API échoue, création en SQLite local.
     */
    public function createFilm(array $data): ?array
    {
        $payload = [
            'title'           => $data['title'],
            'description'     => $data['description'] ?? null,
            'releaseYear'     => (int) ($data['releaseYear'] ?? 0),
            'length'          => (int) ($data['length'] ?? 0),
            'rating'          => $data['rating'] ?? null,
            'rentalDuration'  => (int) ($data['rentalDuration'] ?? 3),
            'rentalRate'      => (float) ($data['rentalRate'] ?? 4.99),
            'replacementCost' => (float) ($data['replacementCost'] ?? 19.99),
        ];

        // Tentative de création via l'API Toad
        try {
            $resp = $this->http()->post($this->baseUrl . '/films', $payload);

            if ($resp->successful()) {
                $created = $resp->json();
                Log::info('Film créé via API Toad', ['filmId' => $created['filmId'] ?? null]);
                return $this->normalizeRemote($created);
            }

            Log::warning('createFilm: API status ' . $resp->status() . ' — fallback local');
        } catch (\Exception $e) {
            Log::warning('createFilm: API indisponible — fallback local. ' . $e->getMessage());
        }

        // Fallback : création en SQLite local
        try {
            $local = LocalFilm::create([
                'filmId'      => null,
                'title'       => $payload['title'],
                'description' => $payload['description'],
                'releaseYear' => $payload['releaseYear'],
                'length'      => $payload['length'],
                'rating'      => $payload['rating'],
            ]);

            $local->filmId = 'local_' . $local->id;
            $local->save();

            Log::info('Film créé localement (fallback)', ['id' => $local->id]);

            return $this->normalizeLocal($local);
        } catch (\Exception $e) {
            Log::error('createFilm (fallback local): ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Met à jour un film.
     *
     * Deux cas :
     *  - Film déjà en local (local pur ou override existant) → mise à jour directe
     *  - Film Peach pur (pas encore en local) → création d'un override local avec le même filmId
     *    La prochaine lecture retournera le local à la place du distant.
     */
    public function updateFilm($id, array $data): ?array
    {
        try {
            $idStr = (string) $id;

            // Chercher un enregistrement local par filmId (override ou local pur)
            $local = LocalFilm::where('filmId', $idStr)->first();

            if (!$local) {
                // Film Peach sans override → créer un nouvel enregistrement local avec le même filmId
                $local = new LocalFilm();
                $local->filmId = $idStr; // Lier à l'ID Peach pour que la fusion l'écrase
            }

            // Mettre à jour les champs (si non fourni, conserver la valeur existante)
            $local->title       = $data['title'];
            $local->description = $data['description'] ?? $local->description;
            $local->releaseYear = (int) ($data['releaseYear'] ?? $local->releaseYear);
            $local->length      = (int) ($data['length'] ?? $local->length);
            $local->rating      = $data['rating'] ?? $local->rating;
            $local->save();

            Log::info('Film mis à jour localement', ['filmId' => $idStr]);

            return $this->normalizeLocal($local);
        } catch (\Exception $e) {
            Log::error('updateFilm: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Supprime un film.
     *
     * Stratégie selon l'origine du film :
     *  - Film purement local (filmId = 'local_*') → suppression SQLite uniquement
     *  - Film distant (filmId numérique) → DELETE via API Toad en priorité,
     *    puis suppression de l'éventuel override local.
     *    Fallback : si l'API échoue, suppression de l'override local uniquement.
     */
    public function deleteFilm($id): bool
    {
        $idStr = (string) $id;

        // Cas 1 : film purement local (jamais envoyé à l'API)
        if (str_starts_with($idStr, 'local_')) {
            try {
                $local = LocalFilm::where('filmId', $idStr)->first();

                if ($local) {
                    $local->delete();
                    Log::info('Film local supprimé', ['filmId' => $idStr]);
                    return true;
                }

                Log::warning("deleteFilm: film local introuvable [$idStr]");
                return false;
            } catch (\Exception $e) {
                Log::error('deleteFilm (local): ' . $e->getMessage());
                return false;
            }
        }

        // Cas 2 : film distant → appel DELETE sur l'API Toad
        $apiSuccess = false;

        try {
            $resp = $this->http()->delete($this->baseUrl . '/films/' . $id);

            if ($resp->successful()) {
                Log::info('Film supprimé via API Toad', ['filmId' => $idStr]);
                $apiSuccess = true;
            } else {
                Log::warning("deleteFilm: API status " . $resp->status() . " pour filmId [$idStr]");
            }
        } catch (\Exception $e) {
            Log::warning('deleteFilm: API indisponible — ' . $e->getMessage());
        }

        // Supprimer également l'éventuel override local (quel que soit le résultat API)
        try {
            $local = LocalFilm::where('filmId', $idStr)
                ->orWhere('id', is_numeric($id) ? (int)$id : null)
                ->first();

            if ($local) {
                $local->delete();
                Log::info('Override local supprimé pour filmId', ['filmId' => $idStr]);
            }
        } catch (\Exception $e) {
            Log::error('deleteFilm (suppression override local): ' . $e->getMessage());
        }

        return $apiSuccess;
    }
}
