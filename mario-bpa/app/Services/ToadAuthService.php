<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ToadAuthService
{
    private ?string $token;
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.toad.url', 'http://localhost:8180'), '/');
        $this->token = config('services.toad.token');
    }

    /**
     * Vérifie les identifiants utilisateur auprès de l'API Toad
     *
     * @param string $email
     * @param string $password
     * @return array|null
     * @throws \RuntimeException Si l'API n'est pas accessible
     */
    public function verify(string $email, string $password): ?array
    {
        if (empty($this->baseUrl)) {
            Log::error('URL de l\'API Toad non configurée');
            throw new \RuntimeException('La configuration de l\'API est incomplète');
        }

        try {
            Log::info('Tentative de connexion à l\'API Toad', [
                'email' => $email,
                'has_token' => !empty($this->token)
            ]);

            // Récupérer tous les staffs
            $request = Http::acceptJson()->timeout(5);

            if (!empty($this->token)) {
                $request = $request->withToken($this->token, 'Bearer');
            }

            $url = $this->baseUrl . '/staffs';
            $response = $request->get($url);

            if (!$response->successful()) {
                Log::warning('Impossible de récupérer la liste des staffs', [
                    'status' => $response->status()
                ]);
                return null;
            }

            $staffs = $response->json();

            // Chercher l'utilisateur par email et vérifier le mot de passe
            foreach ($staffs as $staff) {
                $staffEmail = $staff['email'] ?? '';
                $staffPassword = $staff['password'] ?? '';

                if (strtolower($staffEmail) === strtolower($email)) {
                    // Vérifier le mot de passe (en clair ou hashé)
                    if ($staffPassword === $password || password_verify($password, $staffPassword)) {
                        Log::info('Authentification réussie', ['email' => $email]);

                        // Normaliser les données (snake_case vers camelCase)
                        return [
                            'staffId' => $staff['staffId'] ?? $staff['staff_id'] ?? null,
                            'firstName' => $staff['firstName'] ?? $staff['first_name'] ?? null,
                            'lastName' => $staff['lastName'] ?? $staff['last_name'] ?? null,
                            'email' => $staff['email'] ?? null,
                            'username' => $staff['username'] ?? null,
                            'storeId' => $staff['storeId'] ?? $staff['store_id'] ?? null,
                            'active' => $staff['active'] ?? true,
                        ];
                    }
                }
            }

            Log::warning('Identifiants invalides', ['email' => $email]);
            return null;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Erreur de connexion API Toad', [
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Impossible de se connecter au serveur d\'authentification. Veuillez réessayer plus tard.');

        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('Erreur de requête API Toad', [
                'status' => $e->response->status(),
                'body' => $e->response->body()
            ]);
            throw new \RuntimeException('Le serveur d\'authentification a retourné une erreur. Veuillez réessayer plus tard.');

        } catch (\Exception $e) {
            Log::error('Erreur inattendue avec l\'API Toad', [
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Une erreur inattendue est survenue. Veuillez réessayer plus tard.');
        }
    }
}