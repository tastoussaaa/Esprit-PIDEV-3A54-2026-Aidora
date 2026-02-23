<?php

namespace App\Service;

use OpenAI;

class OpenAIService
{
    private OpenAI\Client $client;

    public function __construct(string $apiKey)
    {
        $this->client = OpenAI::client($apiKey);
    }

    /**
     * Enhance and structure consultation motif (reason) using OpenAI
     */
    public function enhanceConsultationMotif(string $motif): string
    {
        if (!$motif || strlen(trim($motif)) === 0) {
            return $motif;
        }

        try {
            error_log('OpenAI: Starting enhancement for motif: ' . substr($motif, 0, 100));
            
            $response = $this->client->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Tu es un assistant médical francophone. Améliore et structure la raison de consultation en une déclaration médicale claire et professionnelle. Améliore la grammaire, la clarté et la pertinence médicale. Maximum 1-3 phrases. Réponds UNIQUEMENT avec le texte amélioré, sans explications.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $motif
                    ]
                ],
                'max_tokens' => 200,
                'temperature' => 0.5,
            ]);

            if (!isset($response['choices']) || empty($response['choices'])) {
                error_log('OpenAI: No choices in response');
                return $motif;
            }

            $choice = $response['choices'][0];
            if (!isset($choice['message']['content'])) {
                error_log('OpenAI: No content in message');
                return $motif;
            }

            $enhanced = trim($choice['message']['content']);
            error_log('OpenAI: Successfully enhanced. Original: "' . $motif . '" => Enhanced: "' . $enhanced . '"');
            
            return !empty($enhanced) ? $enhanced : $motif;
        } catch (\Exception $e) {
            error_log('OpenAI Enhancement Error: ' . $e->getMessage());
            error_log('OpenAI Stack: ' . $e->getTraceAsString());
            return $motif;
        }
    }

    /**
     * Comprehensive analysis of consultation motif
     */
    public function analyzeMotifComprehensive(string $motif): array
    {
        $cleanMotif = trim($motif);
        
        if (!$cleanMotif || strlen($cleanMotif) === 0) {
            return [
                'enhanced' => $motif,
                'urgency' => 'moderee',
                'isValid' => false,
                'message' => 'Veuillez entrer un motif de consultation.'
            ];
        }

        try {
            error_log('OpenAI: Starting comprehensive analysis for motif: ' . substr($motif, 0, 100));
            
            $systemPrompt = 'Tu es un assistant médical francophone expert. Analyse le motif de consultation et reponds UNIQUEMENT avec un JSON valide sur une seule ligne, sans texte supplémentaire. Format exact: {"enhanced":"texte amélioré","urgency":"elevee|moderee|faible","isValid":true ou false,"message":"message court"}. Règles: urgency=elevee si douleur intense/grave/urgence médicale, moderee si symptômes persistants, faible si routine/check-up. isValid=false seulement si le texte est incompréhensible ou du charabia.';
            
            $userMessage = "Analyse ce motif de consultation et retourne le JSON demandé:\n" . $cleanMotif;

            $response = $this->client->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt
                    ],
                    [
                        'role' => 'user',
                        'content' => $userMessage
                    ]
                ],
                'max_tokens' => 300,
                'temperature' => 0.2,
            ]);

            $content = $response->choices[0]->message->content;
            error_log('OpenAI comprehensive response: ' . $content);
            
            // Extract JSON from response (in case there's extra text)
            $jsonMatch = preg_match('/\{.*\}/', $content, $matches);
            if (!$jsonMatch) {
                error_log('OpenAI: No JSON found in response: ' . $content);
                return $this->fallbackAnalysis($motif);
            }
            
            $result = json_decode($matches[0], true);
            
            if (!$result || !isset($result['enhanced'])) {
                error_log('OpenAI: Failed to parse JSON response: ' . $matches[0]);
                return $this->fallbackAnalysis($motif);
            }

            // Sanitize urgency value
            $urgency = isset($result['urgency']) ? strtolower(trim($result['urgency'])) : 'moderee';
            if (!in_array($urgency, ['elevee', 'moderee', 'faible'])) {
                error_log('OpenAI: Invalid urgency value: ' . $urgency);
                $urgency = 'moderee';
            }
            
            // Sanitize enhanced text
            $enhanced = isset($result['enhanced']) ? trim($result['enhanced']) : $motif;
            
            // Sanitize isValid
            $isValid = isset($result['isValid']) ? (bool)$result['isValid'] : true;
            
            // Sanitize message
            $message = isset($result['message']) ? trim($result['message']) : '';
            if (empty($message)) {
                $message = $isValid ? 'Motif valide et amélioré.' : 'Veuillez reformuler votre motif.';
            }

            return [
                'enhanced' => $enhanced,
                'urgency' => $urgency,
                'isValid' => $isValid,
                'message' => $message
            ];

        } catch (\Exception $e) {
            error_log('OpenAI Comprehensive Analysis Error: ' . $e->getMessage());
            error_log('OpenAI Stack: ' . $e->getTraceAsString());
            return $this->fallbackAnalysis($motif);
        }
    }

    /**
     * Fallback analysis when OpenAI fails
     */
    private function fallbackAnalysis(string $motif): array
    {
        $cleanMotif = trim($motif);
        $motifLower = mb_strtolower($cleanMotif);
        
        if (strlen($cleanMotif) < 3) {
            return [
                'enhanced' => $motif,
                'urgency' => 'moderee',
                'isValid' => false,
                'message' => 'Votre message est trop court. Veuillez décrire vos symptômes plus en détail.'
            ];
        }
        
        $suspiciousPatterns = [
            'abc', 'xyz', '123', '!!!', '???', 'xxx', 'aaa', 
            'bbb', 'ccc', 'ddd', 'eee', 'fff', 'ggg',
            'qwerty', 'asdf', 'zxcv', '111', '222', '333',
            'test', 'testing', 'blah', 'foo', 'bar', 'baz',
            'random', 'nonsense', 'charabia'
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (stripos($cleanMotif, $pattern) !== false) {
                return [
                    'enhanced' => $motif,
                    'urgency' => 'moderee',
                    'isValid' => false,
                    'message' => 'Votre message semble incompréhensible. Veuillez entrer un motif de consultation valide.'
                ];
            }
        }
        
        if (preg_match('/(.)\1{2,}/', $cleanMotif)) {
            return [
                'enhanced' => $motif,
                'urgency' => 'moderee',
                'isValid' => false,
                'message' => 'Votre message contient des caractères répétés. Veuillez entrer un motif valide.'
            ];
        }
        
        if (preg_match('/^[0-9]+$/', $cleanMotif)) {
            return [
                'enhanced' => $motif,
                'urgency' => 'moderee',
                'isValid' => false,
                'message' => 'Les chiffres seuls ne sont pas un motif valide. Veuillez décrire vos symptômes.'
            ];
        }

        $urgencyKeywords = [
            'elevee' => [
                'douleur intense', 'forte douleur', 'saignement', 'difficulté respirer', 
                'urgence', 'grave', 'inconscient', 'douleur thoracique', 'accident',
                'crise', 'étouffement', 'convulsion', 'infarctus',
                'brûlure grave', 'fracture', 'hemorragie'
            ],
            'faible' => [
                'check-up', 'bilan', 'routine', 'prévention', 'vaccin', 
                'renouvellement', 'ordonnance', 'certificat', 'visite de contrôle'
            ]
        ];

        $urgency = 'moderee';
        
        foreach ($urgencyKeywords['elevee'] as $keyword) {
            if (strpos($motifLower, $keyword) !== false) {
                $urgency = 'elevee';
                break;
            }
        }
        
        if ($urgency === 'moderee') {
            foreach ($urgencyKeywords['faible'] as $keyword) {
                if (strpos($motifLower, $keyword) !== false) {
                    $urgency = 'faible';
                    break;
                }
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
     * Generate suggested medical keywords from consultation motif
     */
    public function generateMedicalKeywords(string $motif): array
    {
        try {
            $response = $this->client->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Tu es un assistant médical francophone. Extrais 3-5 mots-clés médicaux pertinents de la raison de consultation. Retourne UNIQUEMENT les mots-clés séparés par des virgules, sans explications ni texte supplémentaire.'
                    ],
                    [
                        'role' => 'user',
                        'content' => 'Mots-clés de: ' . $motif
                    ]
                ],
                'max_tokens' => 100,
                'temperature' => 0.3,
            ]);

            $keywords = trim($response->choices[0]->message->content);
            $keywordArray = array_map('trim', explode(',', $keywords));
            // Filter out empty values
            return array_filter($keywordArray, fn($k) => !empty($k));
        } catch (\Exception $e) {
            error_log('OpenAI Keywords Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Analyze symptom severity from consultation description
     */
    public function analyzeSeverity(string $motif): string
    {
        try {
            $response = $this->client->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Tu es un assistant médical expert. Analyse la gravité des symptômes décrits. Réponds avec UN SEUL mot: leger, modere, ou grave. Rien d\'autre.'
                    ],
                    [
                        'role' => 'user',
                        'content' => 'Gravité: ' . $motif
                    ]
                ],
                'max_tokens' => 20,
                'temperature' => 0.2,
            ]);

            $severity = strtolower(trim($response->choices[0]->message->content));
            
            // Map French responses to standard format
            $severityMap = [
                'leger' => 'mild',
                'léger' => 'mild',
                'modere' => 'moderate',
                'modéré' => 'moderate',
                'grave' => 'severe',
                'mild' => 'mild',
                'moderate' => 'moderate',
                'severe' => 'severe'
            ];
            
            $mappedSeverity = $severityMap[$severity] ?? 'moderate';
            
            if (in_array($mappedSeverity, ['mild', 'moderate', 'severe'])) {
                return $mappedSeverity;
            }
            
            return 'moderate';
        } catch (\Exception $e) {
            error_log('OpenAI Severity Analysis Error: ' . $e->getMessage());
            return 'moderate';
        }
    }
}
