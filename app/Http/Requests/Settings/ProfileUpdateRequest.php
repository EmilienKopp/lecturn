<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'social_x_handle' => ['sometimes', 'nullable', 'string', 'max:100'],
            'social_github_handle' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Normalize social handles: trim, strip a leading @, and treat blanks as null.
     */
    protected function prepareForValidation(): void
    {
        $normalize = static function (mixed $value): ?string {
            if (! is_string($value)) {
                return null;
            }

            $handle = ltrim(trim($value), '@');

            return $handle === '' ? null : $handle;
        };

        $handles = [];

        foreach (['social_x_handle', 'social_github_handle'] as $field) {
            if ($this->has($field)) {
                $handles[$field] = $normalize($this->input($field));
            }
        }

        if ($handles !== []) {
            $this->merge($handles);
        }
    }
}
