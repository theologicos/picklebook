<?php

namespace App\Http\Requests\Auth;

use App\Rules\PhMobileNumber;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Normalise first so the "unique" check compares 09XXXXXXXXX with 09XXXXXXXXX.
        $this->merge([
            'mobile_number' => PhoneNumber::normalize($this->input('mobile_number')) ?? $this->input('mobile_number'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'mobile_number' => ['required', 'string', new PhMobileNumber, 'unique:users,mobile_number'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // Walk-in customers already have an account created by the owner, so point them somewhere useful.
            'mobile_number.unique' => __('This mobile number already has an account. Please log in, or message us if you have booked with us before and need access.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'first_name' => __('first name'),
            'last_name' => __('last name'),
            'mobile_number' => __('mobile number'),
            'password' => __('password'),
        ];
    }
}
