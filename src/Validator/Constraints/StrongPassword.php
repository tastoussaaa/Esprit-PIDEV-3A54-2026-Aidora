<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class StrongPassword extends Constraint
{
    public string $message = 'Le mot de passe doit contenir au moins 8 caractères, dont au moins une lettre majuscule, une lettre minuscule, un chiffre et un caractère spécial.';
    
    /**
     * @param array<string, mixed>|null $options
     */
    public function __construct(
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
        ?array $options = null
    ) {
        parent::__construct($options ?? [], $groups, $payload);
        
        if ($message !== null) {
            $this->message = $message;
        }
    }
}
