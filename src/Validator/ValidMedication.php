<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ValidMedication extends Constraint
{
    public function __construct(
        public string $message = 'Le médicament "{{ medicament }}" n\'a pas été trouvé dans la base de données RxNorm. Veuillez vérifier l\'orthographe.',
        ...$options
    ) {
        parent::__construct(...$options);
    }
}
