<?php

namespace App\Controller;

use App\Service\MedicationApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/medication')]
class MedicationController extends AbstractController
{
    public function __construct(private MedicationApiService $medicationService) {}

    /**
     * Search for medications by name
     * GET /api/medication/search?q=aspirin
     */
    #[Route('/search', name: 'medication_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');

        if (strlen($query) < 2) {
            return $this->json([
                'error' => 'Query must be at least 2 characters',
                'medications' => [],
            ], 400);
        }

        try {
            $medications = $this->medicationService->searchMedications($query);

            return $this->json([
                'query' => $query,
                'count' => count($medications),
                'medications' => $medications,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to search medications: ' . $e->getMessage(),
                'medications' => [],
            ], 500);
        }
    }

    /**
     * Get medication details and available strengths
     * GET /api/medication/12345/details
     */
    #[Route('/{rxnormId}/details', name: 'medication_details', methods: ['GET'])]
    public function getDetails(string $rxnormId): JsonResponse
    {
        try {
            $details = $this->medicationService->getMedicationDetails($rxnormId);

            if (empty($details)) {
                return $this->json([
                    'error' => 'Medication not found',
                ], 404);
            }

            return $this->json($details);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to fetch medication details: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check for drug interactions
     * GET /api/medication/interactions?drug1=123&drug2=456
     */
    #[Route('/interactions', name: 'medication_interactions', methods: ['GET'])]
    public function checkInteractions(Request $request): JsonResponse
    {
        $drug1 = $request->query->get('drug1', '');
        $drug2 = $request->query->get('drug2', '');

        if (empty($drug1) || empty($drug2)) {
            return $this->json([
                'error' => 'Both drug1 and drug2 parameters are required',
            ], 400);
        }

        try {
            $interactions = $this->medicationService->checkDrugInteraction($drug1, $drug2);

            return $this->json([
                'drug1' => $drug1,
                'drug2' => $drug2,
                'has_interactions' => !empty($interactions),
                'interactions' => $interactions,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to check interactions: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate medication dosage format
     * POST /api/medication/validate-dosage
     */
    #[Route('/validate-dosage', name: 'medication_validate_dosage', methods: ['POST'])]
    public function validateDosage(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $dosage = $data['dosage'] ?? '';
        $medicineName = $data['medicine'] ?? '';

        if (empty($dosage)) {
            return $this->json([
                'error' => 'Dosage is required',
                'valid' => false,
            ], 400);
        }

        try {
            $isValidFormat = $this->medicationService->validateDosageFormat($dosage);
            $parsed = $this->medicationService->parseDosage($dosage);

            $response = [
                'dosage' => $dosage,
                'valid_format' => $isValidFormat,
                'parsed' => $parsed,
            ];

            // Check safety if medicine name provided
            if (!empty($medicineName) && $parsed['valid']) {
                $isSafe = $this->medicationService->isSafeDosage($medicineName, $parsed);
                $response['safe_dosage'] = $isSafe;
                $response['message'] = $isSafe === null 
                    ? 'Unknown medicine - cannot verify safety' 
                    : ($isSafe ? 'Dosage is within safe limits' : 'WARNING: Dosage exceeds recommended limits');
            }

            return $this->json($response);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to validate dosage: ' . $e->getMessage(),
                'valid' => false,
            ], 500);
        }
    }
}
