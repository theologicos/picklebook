<?php

namespace App\Rules;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PhMobileNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! PhoneNumber::isValid(is_string($value) ? $value : null)) {
            $fail(__('Enter a valid Philippine mobile number, like 0917 123 4567.'));
        }
    }
}
