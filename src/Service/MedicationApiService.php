<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

class MedicationApiService
{
    private const RXNORM_BASE_URL = 'https://rxnav.nlm.nih.gov/REST';
    
    public function __construct(private HttpClientInterface $httpClient) {}

    /**
     * Search for medication by name using RxNorm API
     * Free and no API key required
        *
        * @return list<array{name: string, rxnorm_id: string, tty: string}>
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
        *
        * @return array{rxnorm_id: string, strengths: list<string>, related_drugs: list<string>}
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
            return [
                'rxnorm_id' => $rxnormId,
                'strengths' => [],
                'related_drugs' => [],
            ];
        }
    }

    /**
     * Check for drug interactions between two medications
     * Returns true if interaction found, false otherwise
        *
        * @return list<array{severity: string, description: string}>
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
        *
        * @return array{quantity: float|null, unit: string|null, valid: bool}
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
        *
        * @param array{quantity: float|null, unit: string|null, valid: bool} $parsedDosage
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
            return false;
        }

        $limits = $maxDosagesPerDay[$medicineLower];
        $unit = $parsedDosage['unit'] ?? '';

        if (isset($limits[$unit])) {
            $quantity = $parsedDosage['quantity'];

            return is_numeric($quantity) && $quantity <= $limits[$unit];
        }

        return false;
    }

    /**
     * Get dosage and instructions for a medication using RxNorm API
     * This searches for the medication and returns available strengths/dosages
     *
     * @return array<string, mixed>
     */
    public function getDosageInfo(string $medicineName): array
    {
        try {
            // First search for the medication to get RxNorm IDs
            $response = $this->httpClient->request(
                'GET',
                self::RXNORM_BASE_URL . "/drugs.json",
                [
                    'query' => ['name' => $medicineName],
                ]
            );

            $data = $response->toArray();
            
            $results = [
                'medication' => $medicineName,
                'strengths' => [],
                'dosage_instructions' => [],
                'found' => false,
            ];

            if (isset($data['drugGroup']['conceptGroup'])) {
                $results['found'] = true;
                
                foreach ($data['drugGroup']['conceptGroup'] as $group) {
                    if (isset($group['conceptProperties'])) {
                        foreach ($group['conceptProperties'] as $concept) {
                            $name = $concept['name'] ?? '';
                            // Extract strength from the name (e.g., "Aspirin 500 MG Oral Tablet")
                            if (preg_match('/(\d+\.?\d*)\s*(mg|g|mcg|ml|iu)/i', $name, $matches)) {
                                $results['strengths'][] = [
                                    'full_name' => $name,
                                    'strength' => $matches[1],
                                    'unit' => strtolower($matches[2]),
                                    'rxnorm_id' => $concept['rxcui'] ?? '',
                                ];
                            }
                        }
                    }
                }
            }

            // If we found results, get more details about the first match
            if (!empty($results['strengths'])) {
                $firstRxnormId = $results['strengths'][0]['rxnorm_id'];
                $detailsResponse = $this->httpClient->request(
                    'GET',
                    self::RXNORM_BASE_URL . "/rxcui/{$firstRxnormId}/allProperties.json?prop=properties"
                );
                
                try {
                    $detailsData = $detailsResponse->toArray();
                    if (isset($detailsData['properties']['property'])) {
                        foreach ($detailsData['properties']['property'] as $prop) {
                            if ($prop['propName'] === 'RXN_STRENGTH' || $prop['propName'] === 'STRENGTH') {
                                $results['dosage_instructions'][] = $prop['propValue'];
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Property lookup failed, continue with basic strength info
                }
            }

            // Add common dosage instructions based on medication name
            $commonInstructions = $this->getCommonDosageInstructions($medicineName);
            if (!empty($commonInstructions)) {
                $results['dosage_instructions'] = array_merge($results['dosage_instructions'], $commonInstructions);
            }

            // Remove duplicates
            $results['strengths'] = array_values(array_unique($results['strengths'], SORT_REGULAR));
            $results['dosage_instructions'] = array_values(array_unique($results['dosage_instructions']));

            return $results;
        } catch (HttpExceptionInterface $e) {
            error_log('RxNorm Dosage Info Error: ' . $e->getMessage());
            return [
                'medication' => $medicineName,
                'strengths' => [],
                'dosage_instructions' => [],
                'found' => false,
                'error' => 'Failed to fetch dosage information',
            ];
        }
    }

    /**
     * Get common dosage instructions for known medications
        *
        * @return list<string>
     */
    private function getCommonDosageInstructions(string $medicineName): array
    {
        $medicationLower = strtolower($medicineName);
        
        $commonDosages = [
            'morphine' => [
                '10-30 mg every 4 hours as needed for pain',
                'May cause drowsiness - do not drive or operate machinery',
            ],
            'oxycodone' => [
                '5-15 mg every 4-6 hours as needed for pain',
                'Take with food to minimize nausea',
            ],
            'ibuprofen' => [
                '200-400 mg every 4-6 hours with food',
                'Maximum 1200mg per day over-the-counter',
            ],
            'aspirin' => [
                '325-650 mg every 4-6 hours with food',
                'Take with food to avoid stomach irritation',
            ],
            'acetaminophen' => [
                '325-650 mg every 4-6 hours',
                'Maximum 3000mg per day (unless directed by doctor)',
            ],
            'paracetamol' => [
                '500-1000 mg every 4-6 hours',
                'Maximum 4g per day',
            ],
            'amoxicillin' => [
                '250-500 mg three times daily',
                'Take with or without food',
            ],
            'azithromycin' => [
                '500 mg once daily for 5 days',
                'Take on an empty stomach (1 hour before or 2 hours after meals)',
            ],
            'metformin' => [
                '500 mg twice daily with meals',
                'Start with low dose and gradually increase',
            ],
            'lisinopril' => [
                '10-40 mg once daily',
                'May cause dizziness - rise slowly from sitting position',
            ],
            'amlodipine' => [
                '5-10 mg once daily',
                'Take at the same time each day',
            ],
            'omeprazole' => [
                '20-40 mg once daily before breakfast',
                'Swallow whole - do not crush or chew',
            ],
            'losartan' => [
                '50-100 mg once daily',
                'May cause dizziness - avoid sudden position changes',
            ],
            'metoprolol' => [
                '25-100 mg twice daily',
                'Take with food to reduce stomach upset',
            ],
            'atorvastatin' => [
                '10-80 mg once daily',
                'Take at the same time each day',
            ],
        ];

        // Check for partial matches
        foreach ($commonDosages as $key => $instructions) {
            if (strpos($medicationLower, $key) !== false) {
                return $instructions;
            }
        }

        return [];
    }
}
