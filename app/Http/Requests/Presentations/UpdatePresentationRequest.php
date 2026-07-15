<?php

namespace App\Http\Requests\Presentations;

use App\Domain\Presentation\ValueObjects\PresentationContent;
use App\Domain\Presentation\ValueObjects\SlideLayout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePresentationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Coarse structural rules only — deep invariants (slot/layout match,
     * block shape) are enforced by PresentationContent::fromArray().
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'content' => ['sometimes', 'array'],
            'content.version' => ['required_with:content', Rule::in([PresentationContent::VERSION])],
            'content.slides' => ['required_with:content', 'array'],
            'content.slides.*.id' => ['required', 'string'],
            'content.slides.*.layout' => ['required', Rule::enum(SlideLayout::class)],
            'content.slides.*.slots' => ['sometimes', 'array'],
        ];
    }
}
