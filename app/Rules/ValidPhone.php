<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Teléfono local de 8 dígitos (ej. 7012-3456) o internacional de 10 a 15 dígitos (ej. +1 415 523 8886).
 */
class ValidPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = (string) $value;
        $digits = preg_replace('/\D/', '', $value);

        if (! preg_match('/^[\d\s\-\+\(\)]+$/', $value) || ! (strlen($digits) === 8 || (strlen($digits) >= 10 && strlen($digits) <= 15))) {
            $fail('El :attribute debe tener 8 dígitos (ej. 7012-3456) o incluir el código de país.');
        }
    }
}
