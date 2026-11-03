<?php

namespace App\Http\Requests\Auth;

use App\Rules\PhMobileNumber;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // "+63 917 123 4567" and "0917-123-4567" are the same account.
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
            'mobile_number' => ['required', 'string', new PhMobileNumber],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        if (! Auth::attempt($this->only('mobile_number', 'password'), $this->boolean('remember'))) {
            throw ValidationException::withMessages([
                'mobile_number' => __('The mobile number or password is incorrect.'),
            ]);
        }
    }
}
