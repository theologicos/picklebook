<?php

namespace App\Http\Requests\Admin;

use App\Enums\SkillLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class OpenPlaySessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // return array<string, array<int, mixed>>
    public function rules(): array
    {
        return [
            'court_id' => ['required', 'integer', 'exists:courts,id'],
            'title' => ['required', 'string', 'max:100'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hour' => ['required', 'integer', 'between:0,23'],
            'hours' => ['required', 'integer', 'between:1,12'],
            'max_players' => ['required', 'integer', 'between:4,40'],
            'fee_per_player' => ['required', 'integer', 'min:0', 'max:5000'],
            'skill_level' => ['required', Rule::enum(SkillLevel::class)],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function startsAt(): Carbon
    {
        return Carbon::createFromFormat('!Y-m-d', $this->input('date'))->setTime($this->integer('hour'), 0);
    }
}