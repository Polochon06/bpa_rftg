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

        $url = $this->baseUrl . '/staffs/verify';
        $body = [
            'email' => $email,
            'password' => $password
        ];

        try {
            Log::info('Tentative de connexion à l\'API Toad', [
                'url' => $url,
                'email' => $email,
                'has_token' => !empty($this->token)
            ]);

            $request = Http::acceptJson()
                ->timeout(5);

            // Ajoute le token Bearer si configuré
            if (!empty($this->token)) {
                $request = $request->withToken($this->token, 'Bearer'); 
            }

            $response = $request->post($url, $body);

            $status = $response->status();
            $responseBody = $response->json();

            Log::info('Réponse /verify', [
                'status' => $status,
                'body' => $responseBody
            ]);

            if ($response->successful()) {
                return $responseBody;
            }

            Log::warning('Verify KO', [
                'status' => $status,
                'body' => $responseBody
            ]);
            return null;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Erreur de connexion API Toad', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Impossible de se connecter au serveur d\'authentification. Veuillez réessayer plus tard.');
            
        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('Erreur de requête API Toad', [
                'url' => $url,
                'status' => $e->response->status(),
                'body' => $e->response->body()
            ]);
            throw new \RuntimeException('Le serveur d\'authentification a retourné une erreur. Veuillez réessayer plus tard.');
            
        } catch (\Exception $e) {
            Log::error('Erreur inattendue avec l\'API Toad', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Une erreur inattendue est survenue. Veuillez réessayer plus tard.');
        }
    }
}