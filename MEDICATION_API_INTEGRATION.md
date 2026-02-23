# Medication API Integration

## Overview
Integrated RxNorm (National Library of Medicine) medication database API to validate prescriptions, check drug interactions, and ensure dosage safety.

## Files Created

### 1. **MedicationApiService** (`src/Service/MedicationApiService.php`)
Core service that handles all medication API calls to RxNorm.

**Methods:**
- `searchMedications(string $name): array` - Search medications by name
- `getMedicationDetails(string $rxnormId): array` - Get medication strengths and details
- `checkDrugInteraction(string $id1, string $id2): array` - Check interactions between two drugs
- `validateDosageFormat(string $dosage): bool` - Validate dosage format (e.g., "500mg")
- `parseDosage(string $dosage): array` - Parse dosage into quantity and unit
- `isSafeDosage(string $medicine, array $parsed): bool` - Check if dosage is within safe limits

### 2. **MedicationValidator** (`src/Validator/ValidMedication.php` & `ValidMedicationValidator.php`)
Symfony Validator constraint that validates medications against RxNorm database.

**Usage in Entity:**
```php
#[ValidMedication]
private ?string $medicament = null;
```

### 3. **MedicationController** (`src/Controller/MedicationController.php`)
REST API endpoints for medication operations.

## API Endpoints

### 1. Search Medications
**GET** `/api/medication/search?q=aspirin`

**Response:**
```json
{
  "query": "aspirin",
  "count": 5,
  "medications": [
    {
      "name": "Aspirin 325 MG Oral Tablet",
      "rxnorm_id": "203742",
      "tty": "SBD"
    },
    {
      "name": "Aspirin 500 MG Oral Tablet",
      "rxnorm_id": "203743",
      "tty": "SBD"
    }
  ]
}
```

### 2. Get Medication Details
**GET** `/api/medication/203742/details`

**Response:**
```json
{
  "rxnorm_id": "203742",
  "strengths": [
    "Aspirin 325 MG",
    "Aspirin 500 MG"
  ],
  "related_drugs": [...]
}
```

### 3. Check Drug Interactions
**GET** `/api/medication/interactions?drug1=203742&drug2=204501`

**Response:**
```json
{
  "drug1": "203742",
  "drug2": "204501",
  "has_interactions": true,
  "interactions": [
    {
      "severity": "moderate",
      "description": "May increase the risk of bleeding"
    }
  ]
}
```

### 4. Validate Dosage
**POST** `/api/medication/validate-dosage`

**Request:**
```json
{
  "dosage": "500mg",
  "medicine": "aspirin"
}
```

**Response:**
```json
{
  "dosage": "500mg",
  "valid_format": true,
  "parsed": {
    "quantity": 500,
    "unit": "mg",
    "valid": true
  },
  "safe_dosage": true,
  "message": "Dosage is within safe limits"
}
```

## Integration with Ordonnance Entity

The `Ordonnance` entity now validates medications automatically:

```php
$ordonnance = new Ordonnance();
$ordonnance->setMedicament('Aspirin 500mg'); // Validated against RxNorm

// If invalid medication, validation error is raised:
// "The medication 'InvalidDrug123' is not recognized in the medication database."
```

## Configuration

### Environment Variables
Add to `.env`:
```
MEDICATION_API_ENABLED=true
```

### Services Configuration
The service is auto-wired. In `services.yaml`, the service is automatically registered.

## Usage Examples

### 1. In Controller
```php
use App\Service\MedicationApiService;

class MyController extends AbstractController
{
    public function __construct(private MedicationApiService $medicationService) {}
    
    public function prescribeMedication(): Response
    {
        // Search medications
        $medications = $this->medicationService->searchMedications('ibuprofen');
        
        // Check interactions
        $interactions = $this->medicationService->checkDrugInteraction('123', '456');
        
        // Validate dosage
        $isSafe = $this->medicationService->isSafeDosage('ibuprofen', [
            'quantity' => 400,
            'unit' => 'mg',
        ]);
        
        return $this->json(['safe' => $isSafe]);
    }
}
```

### 2. In Form (Auto Validation)
```php
// In OrdonnanceType form
$form = $this->createForm(OrdonnanceType::class, $ordonnance);

// If medication is invalid, form will show validation error automatically
if (!$form->isValid()) {
    // Validation errors triggered by ValidMedicationValidator
}
```

### 3. In JavaScript/Frontend
```javascript
// Search medications
fetch('/api/medication/search?q=aspirin')
  .then(r => r.json())
  .then(data => {
    console.log('Available medications:', data.medications);
  });

// Check interactions
fetch('/api/medication/interactions?drug1=203742&drug2=204501')
  .then(r => r.json())
  .then(data => {
    if (data.has_interactions) {
      alert('WARNING: Drug interaction detected!');
    }
  });

// Validate dosage
fetch('/api/medication/validate-dosage', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ dosage: '500mg', medicine: 'aspirin' })
})
  .then(r => r.json())
  .then(data => {
    if (!data.valid_format) alert('Invalid dosage format');
  });
```

## RxNorm API Details

- **Base URL:** `https://rxnav.nlm.nih.gov/REST`
- **No API Key Required:** Free, public API
- **Rate Limit:** Reasonable limits for healthcare applications
- **Data:** Comprehensive U.S. drug database maintained by NLM
- **Response Format:** JSON

## Benefits

| Benefit | Purpose |
|---------|---------|
| **Medication Validation** | Ensures doctors prescribe real, recognized medications |
| **Dosage Checking** | Prevents over/under-dosing errors |
| **Drug Interactions** | Alerts on dangerous drug combinations |
| **Safety Checks** | Reduces medical errors and patient harm |
| **Free API** | No cost, no key required |

## Limitations

1. **No Real-Time Validation on Form Submit** (optional) - Can disable to avoid slowdown
2. **Dosage Limits are Basic** - Customize `isSafeDosage()` with your medical protocols
3. **U.S. Database Only** - May not have international medications
4. **API Latency** - RxNorm API can be slow; consider caching results

## Future Enhancements

1. Add caching layer for frequently searched medications
2. Implement async validation on form submit
3. Add FDA adverse event reporting integration
4. Implement pharmacy inventory check
5. Add patient allergy cross-reference checking
6. Integrate with insurance formulary databases

## Testing the Integration

```bash
# Test medication search
curl -X GET "http://localhost/api/medication/search?q=aspirin"

# Test interaction check
curl -X GET "http://localhost/api/medication/interactions?drug1=203742&drug2=204501"

# Test dosage validation
curl -X POST "http://localhost/api/medication/validate-dosage" \
  -H "Content-Type: application/json" \
  -d '{"dosage":"500mg","medicine":"aspirin"}'
```

## Notes

- RxNorm is maintained by the National Library of Medicine (NLM), part of NIH
- All data is public and freely available
- Consider adding caching to reduce API calls
- Can be extended with FDA Adverse Event Reporting System (FAERS) data in future
