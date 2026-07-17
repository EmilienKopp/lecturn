<?php

namespace App\Http\Requests\Presentations;

use App\Presentation\ExportFormat;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportPresentationRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'format' => ['required', Rule::enum(ExportFormat::class)],
        ];
    }

    public function exportFormat(): ExportFormat
    {
        return ExportFormat::from($this->validated('format'));
    }
}
