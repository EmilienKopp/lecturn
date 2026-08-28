<?php

namespace App\Http\Requests\Presentations;

use App\Domain\Presentation\ValueObjects\FlowGraph;
use App\Domain\Presentation\ValueObjects\FlowNodeType;
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
            'content.backgroundImage' => ['sometimes', 'nullable', 'string'],
            'content.slides' => ['required_with:content', 'array'],
            'content.slides.*.id' => ['required', 'string'],
            'content.slides.*.layout' => ['required', Rule::enum(SlideLayout::class)],
            'content.slides.*.slots' => ['sometimes', 'array'],
            'flow' => ['sometimes', 'array'],
            'flow.version' => ['required_with:flow', Rule::in([FlowGraph::VERSION])],
            'flow.nodes' => ['present_with:flow', 'array'],
            'flow.nodes.*.id' => ['required', 'string'],
            'flow.nodes.*.type' => ['required', Rule::enum(FlowNodeType::class)],
            'flow.nodes.*.position' => ['required', 'array'],
            'flow.nodes.*.position.x' => ['required', 'numeric'],
            'flow.nodes.*.position.y' => ['required', 'numeric'],
            'flow.nodes.*.data' => ['sometimes', 'array'],
            'flow.edges' => ['present_with:flow', 'array'],
            'flow.edges.*.id' => ['required', 'string'],
            'flow.edges.*.source' => ['required', 'string'],
            'flow.edges.*.target' => ['required', 'string'],
            'flow.edges.*.label' => ['sometimes', 'nullable', 'string'],
            'talk_settings' => ['sometimes', 'array'],
            'talk_settings.showReactions' => ['sometimes', 'boolean'],
            'talk_settings.showDock' => ['sometimes', 'boolean'],
            'talk_settings.timerMode' => ['sometimes', 'string', Rule::in(['elapsed', 'countdown'])],
            'talk_settings.durationMinutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:480'],
        ];
    }
}
