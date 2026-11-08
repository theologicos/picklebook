<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class GcashReference implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = preg_replace('/\D+/', '', is_string($value) ? $value : '') ?? '';

        if (strlen($digits) < 10 || strlen($digits) > 20) {
            $fail(__('Enter the reference number from your GCash receipt (10 to 20 digits).'));
        }
    }
}