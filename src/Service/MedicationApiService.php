<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

class MedicationApiService
{
    private const OPENFDA_BASE_URL = 'https://api.fda.gov/drug/event.json';
    private const RXNORM_BASE_URL = 'https://rxnav.nlm.nih.gov/REST';
    
    public function __construct(private HttpClientInterface $httpClient) {}

    /**
     * Search for medication by name using RxNorm API
     * Free and no API key required
     */
    public function searchMedications(string $medicineName): array
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                self::RXNORM_BASE_URL . "/drugs.json",
                [
                    'query' => ['name' => $medicineName],
                ]
            );

            $data = $response->toArray();
            
            if (isset($data['drugGroup']['conceptGroup'])) {
                $medications = [];
                foreach ($data['drugGroup']['conceptGroup'] as $group) {
                    if (isset($group['conceptProperties'])) {
                        foreach ($group['conceptProperties'] as $concept) {
                            $medications[] = [
                                'name' => $concept['name'] ?? '',
                                'rxnorm_id' => $concept['rxcui'] ?? '',
                                'tty' => $concept['tty'] ?? '',
                            ];
                        }
                    }
                }
                return $medications;
            }
            
            return [];
        } catch (HttpExceptionInterface $e) {
            error_log('RxNorm API Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get medication details including dosage strengths
     */
    public function getMedicationDetails(string $rxnormId): array
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                self::RXNORM_BASE_URL . "/rxcui/{$rxnormId}/related.json"
            );

            $data = $response->toArray();
            
            $details = [
                'rxnorm_id' => $rxnormId,
                'strengths' => [],
                'related_drugs' => [],
            ];

            if (isset($data['relatedGroup']['conceptGroup'])) {
                foreach ($data['relatedGroup']['conceptGroup'] as $group) {
                    if (isset($group['conceptProperties'])) {
                        foreach ($group['conceptProperties'] as $concept) {
                            $details['strengths'][] = $concept['name'] ?? '';
                        }
                    }
                }
            }

            return $details;
        } catch (HttpExceptionInterface $e) {
            error_log('RxNorm API Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check for drug interactions between two medications
     * Returns true if interaction found, false otherwise
     */
    public function checkDrugInteraction(string $rxnormId1, string $rxnormId2): array
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                self::RXNORM_BASE_URL . "/interaction/list.json",
                [
                    'query' => [
                        'rxcuis' => "{$rxnormId1}+{$rxnormId2}",
                    ],
                ]
            );

            $data = $response->toArray();
            
            if (isset($data['interactionTypeGroup']) && !empty($data['interactionTypeGroup'])) {
                $interactions = [];
                foreach ($data['interactionTypeGroup'] as $group) {
                    if (isset($group['interactionType'])) {
                        foreach ($group['interactionType'] as $interaction) {
                            if (isset($interaction['interactionPair'])) {
                                foreach ($interaction['interactionPair'] as $pair) {
                                    $interactions[] = [
                                        'severity' => $pair['severity'] ?? 'Unknown',
                                        'description' => $pair['description'] ?? '',
                                    ];
                                }
                            }
                        }
                    }
                }
                return $interactions;
            }

            return [];
        } catch (HttpExceptionInterface $e) {
            error_log('RxNorm Interaction Check Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Validate medication dosage format
     * Checks if dosage follows standard patterns
     */
    public function validateDosageFormat(string $dosage): bool
    {
        // Pattern: number + unit (mg, ml, tablets, etc.)
        $pattern = '/^\d+\.?\d*\s*(mg|ml|g|mcg|iu|tablets?|capsules?|drops?|units?)\b/i';
        return (bool) preg_match($pattern, $dosage);
    }

    /**
     * Parse dosage string and extract quantity and unit
     */
    public function parseDosage(string $dosage): array
    {
        $pattern = '/^(\d+\.?\d*)\s*([a-z]+)/i';
        if (preg_match($pattern, $dosage, $matches)) {
            return [
                'quantity' => (float) $matches[1],
                'unit' => strtolower($matches[2]),
                'valid' => true,
            ];
        }
        
        return [
            'quantity' => null,
            'unit' => null,
            'valid' => false,
        ];
    }

    /**
     * Check if dosage is within safe limits
     * Basic validation - adjust per your medical standards
     */
    public function isSafeDosage(string $medicineName, array $parsedDosage): bool
    {
        // This is a simplified example - in production you'd query a dosage database
        $maxDosagesPerDay = [
            'aspirin' => ['mg' => 4000],
            'ibuprofen' => ['mg' => 3200],
            'acetaminophen' => ['mg' => 4000],
            'amoxicillin' => ['mg' => 3000],
        ];

        $medicineLower = strtolower($medicineName);
        
        if (!isset($maxDosagesPerDay[$medicineLower])) {
            // Unknown medicine - cannot validate safety
            return null;
        }

        $limits = $maxDosagesPerDay[$medicineLower];
        $unit = $parsedDosage['unit'] ?? '';

        if (isset($limits[$unit])) {
            return $parsedDosage['quantity'] <= $limits[$unit];
        }

        return null;
    }
}
