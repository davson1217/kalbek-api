<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SpeechCheckRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scenario_id' => ['required', 'string', 'exists:scenarios,slug'],
            'scene_id' => ['required', 'string', 'exists:scenes,slug'],
            'goal_id' => ['required', 'string', 'exists:goals,slug'],
            'audio' => ['required', 'file', 'max:8000'],
            'intent' => ['required', 'string', 'max:400'],
            'example' => ['nullable', 'string', 'max:400'],
            'context' => ['nullable', 'string', 'max:400'],
        ];
    }
}
