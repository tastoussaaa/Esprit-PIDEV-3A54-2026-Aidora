<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class StrongPasswordValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$value) {
            return;
        }

        $password = (string) $value;
        
        // Vérifier la longueur minimale
        if (strlen($password) < 8) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        // Vérifier la présence d'au moins une lettre majuscule
        if (!preg_match('/[A-Z]/', $password)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        // Vérifier la présence d'au moins une lettre minuscule
        if (!preg_match('/[a-z]/', $password)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        // Vérifier la présence d'au moins un chiffre
        if (!preg_match('/[0-9]/', $password)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        // Vérifier la présence d'au moins un caractère spécial
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }
    }
}
