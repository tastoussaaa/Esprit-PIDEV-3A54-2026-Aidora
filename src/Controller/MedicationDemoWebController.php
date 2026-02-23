<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class MedicationDemoWebController extends AbstractController
{
    public function index(): Response
    {
        // Public FHIR sandbox (R4) — fetch one patient
        $fhirUrl = 'https://r4.smarthealthit.org/Patient?_count=1';
        $fhir = @file_get_contents($fhirUrl);
        $fhirData = $fhir ? json_decode($fhir, true) : ['error' => 'FHIR request failed'];

        // RxNav (RxNorm) lookup example
        $rxUrl = 'https://rxnav.nlm.nih.gov/REST/rxcui.json?name=metformin';
        $rx = @file_get_contents($rxUrl);
        $rxData = $rx ? json_decode($rx, true) : ['error' => 'RxNav request failed'];

        // openFDA label lookup example
        $fdaUrl = 'https://api.fda.gov/drug/label.json?search=openfda.brand_name:metformin&limit=1';
        $fda = @file_get_contents($fdaUrl);
        $fdaData = $fda ? json_decode($fda, true) : ['error' => 'openFDA request failed'];

        return $this->render('medication_demo/index.html.twig', [
            'fhir' => $fhirData,
            'rx' => $rxData,
            'fda' => $fdaData,
        ]);
    }

    public function mockRtbc(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $response = [
            'formulary' => 'preferred',
            'copayEstimate' => 8.75,
            'priorAuthRequired' => false,
            'medication' => $data['medication'] ?? null,
        ];

        return new JsonResponse($response);
    }

    public function mockEprescribe(Request $request): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>\n';
        $xml .= '<PrescribeResponse>\n';
        $xml .= '  <status>accepted</status>\n';
        $xml .= '  <prescriptionId>MOCK-'.uniqid().'</prescriptionId>\n';
        $xml .= '</PrescribeResponse>';

        return new Response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
