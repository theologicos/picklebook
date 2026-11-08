<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CourtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the "admin" middleware already guards these routes
    }

    protected function prepareForValidation(): void
    {
        // An unticked checkbox sends nothing, so turn it into false.
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('courts', 'name')->ignore($this->route('court'))],
            'description' => ['nullable', 'string', 'max:255'],
            'hourly_rate' => ['required', 'integer', 'min:0', 'max:100000'],
            'peak_hourly_rate' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['boolean'],
        ];
    }
}