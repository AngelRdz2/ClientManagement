<?php

namespace App\Rules;

use App\Support\Dui;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidDui implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (Dui::normalize((string) $value) === null) {
            $fail('El :attribute debe tener 9 dígitos (formato 00000000-0).');

            return;
        }

        if (! Dui::isValid((string) $value)) {
            $fail('El :attribute no es válido: el dígito verificador no coincide.');
        }
    }
}
