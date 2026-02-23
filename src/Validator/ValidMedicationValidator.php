<?php

namespace App\Validator;

use App\Service\MedicationApiService;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ValidMedicationValidator extends ConstraintValidator
{
    public function __construct(private MedicationApiService $medicationService) {}

    public function validate($value, Constraint $constraint): void
    {
        if (null === $value || '' === $value) {
            return;
        }

        // Search for medication in RxNorm database
        $medications = $this->medicationService->searchMedications($value);

        if (empty($medications)) {
            $this->context
                ->buildViolation($constraint->message)
                ->setParameter('{{ medicament }}', $value)
                ->addViolation();
        }
    }
}
