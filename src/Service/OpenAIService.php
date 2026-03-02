<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenAIService
{
    private const OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';
    
    private string $apiKey;
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient, string $openaiApiKey)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $openaiApiKey;
    }

    /**
     * Enhance and structure consultation motif
     */
    public function enhanceConsultationMotif(string $motif): string
    {
        if (!$motif || strlen(trim($motif)) === 0) {
            return $motif;
        }

        try {
            $response = $this->callOpenAI([
                [
                    'role' => 'system',
                    'content' => 'Tu es un assistant médical francophone. Améliore la raison de consultation en une déclaration claire et professionnelle. Maximum 1-3 phrases. Réponds UNIQUEMENT avec le texte amélioré.'
                ],
                [
                    'role' => 'user',
                    'content' => $motif
                ]
            ], 200, 0.5);

            return $response ?: $motif;

        } catch (\Exception $e) {
            return $motif;
        }
    }

    /**
     * Comprehensive motif analysis with HARD validation
     */
    public function analyzeMotifComprehensive(string $motif): array
    {
        $cleanMotif = trim($motif);

        // =========================
        // HARD VALIDATION (NO AI)
        // =========================

        if (strlen($cleanMotif) < 6) {
            return $this->invalidResponse($motif, 'Veuillez écrire une phrase plus détaillée décrivant vos symptômes.');
        }

        if (preg_match('/^[0-9]+$/', $cleanMotif)) {
            return $this->invalidResponse($motif, 'Les chiffres seuls ne sont pas un motif valide.');
        }

        if (preg_match('/(.)\1{2,}/', $cleanMotif)) {
            return $this->invalidResponse($motif, 'Votre message semble invalide. Veuillez reformuler.');
        }

        if (str_word_count($cleanMotif) < 2) {
            return $this->invalidResponse($motif, 'Veuillez entrer une phrase complète décrivant vos symptômes.');
        }

        if (!preg_match('/[aeiouyàâéèêëîïôûùüÿæœ]/i', $cleanMotif)) {
            return $this->invalidResponse($motif, 'Le texte semble incompréhensible.');
        }

        // =========================
        // AI ANALYSIS
        // =========================

        try {
            $systemPrompt = "Tu es un médecin expert en triage médical. "
                . "Réponds UNIQUEMENT en JSON valide.\n"
                . "Format: {\"enhanced\":\"texte\",\"urgency\":\"elevee|moderee|faible\",\"isValid\":true|false,\"message\":\"texte\"}\n"
                . "isValid=false si le texte n'est pas une phrase médicale claire.";

            $response = $this->callOpenAI([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $cleanMotif]
            ], 300, 0.2);

            if (!preg_match('/\{.*\}/', $response, $matches)) {
                return $this->fallbackAnalysis($motif);
            }

            $result = json_decode($matches[0], true);

            if (!$result) {
                return $this->fallbackAnalysis($motif);
            }

            $urgency = strtolower($result['urgency'] ?? 'moderee');
            if (!in_array($urgency, ['elevee', 'moderee', 'faible'])) {
                $urgency = 'moderee';
            }

            return [
                'enhanced' => trim($result['enhanced'] ?? $motif),
                'urgency' => $urgency,
                'isValid' => (bool)($result['isValid'] ?? true),
                'message' => trim($result['message'] ?? 'Motif enregistré.')
            ];

        } catch (\Exception $e) {
            return $this->fallbackAnalysis($motif);
        }
    }

    /**
     * Call OpenAI API using HTTP Client
     */
    private function callOpenAI(array $messages, int $maxTokens = 200, float $temperature = 0.5): string
    {
        if (empty($this->apiKey)) {
            throw new \Exception('OpenAI API key not configured');
        }

        $response = $this->httpClient->request('POST', self::OPENAI_API_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
            ],
        ]);

        $content = $response->toArray();
        
        return trim($content['choices'][0]['message']['content'] ?? '');
    }

    /**
     * Fallback keyword-based urgency detection
     */
    private function fallbackAnalysis(string $motif): array
    {
        $motifLower = mb_strtolower($motif);
        $urgency = 'moderee';

        $urgentWords = [
            'suicide','mourir','infarctus','avc','crise cardiaque',
            'étouffement','hémorragie','saignement abondant',
            'perte conscience','convulsion','douleur thoracique'
        ];

        foreach ($urgentWords as $word) {
            if (str_contains($motifLower, $word)) {
                $urgency = 'elevee';
                break;
            }
        }

        $lowWords = [
            'check-up','bilan','certificat','ordonnance',
            'contrôle','prévention'
        ];

        foreach ($lowWords as $word) {
            if (str_contains($motifLower, $word)) {
                $urgency = 'faible';
                break;
            }
        }

        return [
            'enhanced' => $motif,
            'urgency' => $urgency,
            'isValid' => true,
            'message' => 'Motif de consultation enregistré.'
        ];
    }

    /**
     * Standard invalid response
     */
    private function invalidResponse(string $motif, string $message): array
    {
        return [
            'enhanced' => $motif,
            'urgency' => 'moderee',
            'isValid' => false,
            'message' => $message
        ];
    }

    /**
     * Analyze severity only
     */
    public function analyzeSeverity(string $motif): string
    {
        try {
            $response = $this->callOpenAI([
                [
                    'role' => 'system',
                    'content' => 'Analyse la gravité et réponds uniquement: leger, modere ou grave.'
                ],
                [
                    'role' => 'user',
                    'content' => $motif]
            ], 20, 0.2);

            $severity = strtolower(trim($response));

            return match ($severity) {
                'leger','léger' => 'mild',
                'grave' => 'severe',
                default => 'moderate',
            };

        } catch (\Exception $e) {
            return 'moderate';
        }
    }
}
