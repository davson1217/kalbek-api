<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
            'display_name' => ['sometimes', 'required', 'string', 'max:255'],
            'avatar_character' => ['sometimes', 'required', 'string', 'exists:characters,slug'],
            'strict_speech_mode' => ['sometimes', 'required', 'boolean'],
            'app_language' => ['sometimes', 'required', 'string', 'in:en,lt'],
            'show_translations' => ['sometimes', 'required', 'boolean'],
            'show_success_feedback' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
