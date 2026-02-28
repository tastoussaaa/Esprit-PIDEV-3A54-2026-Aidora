<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Service d'analyse IA externe basé sur HuggingFace Inference API
 * Utilise GPT-2 ou modèle disponible pour analyser le risque des demandes d'aide
 */
class AiRiskAnalysisService
{
    // Utilisation de gpt2 (toujours gratuit et disponible)
    private const API_URL = 'https://api-inference.huggingface.co/models/gpt2';
    private const TIMEOUT = 15;
    private const MAX_TOKENS = 500;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $huggingfaceApiKey,
    ) {
    }

    /**
     * Analyse le texte d'une demande via IA HuggingFace
     *
     * @return array{niveau_risque:string,score_risque:int,pathologie_probable:string,justification:string}|null
     */
    public function analyzeDemandeText(string $description): ?array
    {
        if (empty($this->huggingfaceApiKey)) {
            $this->logger->warning('HuggingFace API key not configured, AI analysis skipped');
            return null;
        }

        if (empty(trim($description))) {
            $this->logger->debug('Empty description provided for AI analysis');
            return null;
        }

        try {
            $prompt = $this->buildAnalysisPrompt($description);
            $response = $this->callHuggingFaceApi($prompt);

            if ($response === null) {
                return null;
            }

            return $this->parseAiResponse($response);
        } catch (\Exception $e) {
            $this->logger->error('AI risk analysis failed', [
                'error' => $e->getMessage(),
                'description_length' => strlen($description),
            ]);
            return null;
        }
    }

    /**
     * Construit le prompt structuré pour l'analyse IA
     */
    private function buildAnalysisPrompt(string $description): string
    {
        return <<<PROMPT
[INST]
Tu es un expert médical spécialisé dans l'évaluation des besoins en soins à domicile.

Analyse la description suivante d'une demande de soins et détermine :
1. Le niveau de risque médical (faible, moyen, élevé)
2. Un score de risque entre 0 et 100
3. La pathologie probable ou situation médicale
4. Une justification courte de ton analyse

Description du patient :
"{$description}"

IMPORTANT : Réponds UNIQUEMENT avec un objet JSON valide au format suivant, sans texte avant ou après :
{
  "niveau_risque": "faible|moyen|élevé",
  "score_risque": 0-100,
  "pathologie_probable": "description courte",
  "justification": "explication en 1-2 phrases"
}
[/INST]
PROMPT;
    }

    /**
     * Appelle l'API HuggingFace avec gestion d'erreurs robuste
     */
    private function callHuggingFaceApi(string $prompt): ?string
    {
        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'timeout' => self::TIMEOUT,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->huggingfaceApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'inputs' => $prompt,
                    'parameters' => [
                        'max_new_tokens' => self::MAX_TOKENS,
                        'temperature' => 0.3,
                        'top_p' => 0.9,
                        'do_sample' => true,
                        'return_full_text' => false,
                    ],
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                $this->logger->warning('HuggingFace API returned non-200 status', [
                    'status_code' => $statusCode,
                ]);
                return null;
            }

            $data = $response->toArray();

            // HuggingFace retourne un tableau avec le texte généré
            if (!isset($data[0]['generated_text'])) {
                $this->logger->warning('Unexpected HuggingFace API response format', [
                    'response' => $data,
                ]);
                return null;
            }

            return $data[0]['generated_text'];

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('HuggingFace API transport error', [
                'error' => $e->getMessage(),
            ]);
            return null;
        } catch (\Exception $e) {
            $this->logger->error('HuggingFace API unexpected error', [
                'error' => $e->getMessage(),
                'type' => get_class($e),
            ]);
            return null;
        }
    }

    /**
     * Parse et valide la réponse JSON de l'IA
     *
     * @return array{niveau_risque:string,score_risque:int,pathologie_probable:string,justification:string}|null
     */
    private function parseAiResponse(string $rawResponse): ?array
    {
        // Nettoyer la réponse (enlever markdown, espaces, etc.)
        $cleaned = trim($rawResponse);
        $cleaned = preg_replace('/^```json\s*/', '', $cleaned);
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);
        $cleaned = trim($cleaned);

        // Extraire le JSON si présent dans du texte
        if (preg_match('/\{[^{}]*"niveau_risque"[^{}]*\}/', $cleaned, $matches)) {
            $cleaned = $matches[0];
        }

        try {
            $decoded = json_decode($cleaned, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($decoded)) {
                $this->logger->warning('AI response is not an array', ['response' => $rawResponse]);
                return null;
            }

            // Validation des champs obligatoires
            $required = ['niveau_risque', 'score_risque', 'pathologie_probable', 'justification'];
            foreach ($required as $field) {
                if (!isset($decoded[$field])) {
                    $this->logger->warning('Missing required field in AI response', [
                        'missing_field' => $field,
                        'response' => $decoded,
                    ]);
                    return null;
                }
            }

            // Normalisation et validation
            $niveauRisque = strtolower(trim($decoded['niveau_risque']));
            if (!in_array($niveauRisque, ['faible', 'moyen', 'élevé'], true)) {
                $this->logger->warning('Invalid niveau_risque value', ['value' => $niveauRisque]);
                $niveauRisque = 'moyen'; // fallback
            }

            $scoreRisque = (int) $decoded['score_risque'];
            $scoreRisque = max(0, min(100, $scoreRisque)); // clamp 0-100

            return [
                'niveau_risque' => $niveauRisque,
                'score_risque' => $scoreRisque,
                'pathologie_probable' => trim((string) $decoded['pathologie_probable']),
                'justification' => trim((string) $decoded['justification']),
            ];

        } catch (\JsonException $e) {
            $this->logger->warning('Failed to parse AI response as JSON', [
                'error' => $e->getMessage(),
                'response' => substr($rawResponse, 0, 500),
            ]);
            return null;
        }
    }

    /**
     * Vérifie si le service IA est disponible
     */
    public function isAvailable(): bool
    {
        return !empty($this->huggingfaceApiKey);
    }
}
