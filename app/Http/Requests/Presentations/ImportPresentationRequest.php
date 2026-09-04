<?php

namespace App\Http\Requests\Presentations;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class ImportPresentationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['nullable', 'file', 'max:5120'],
            'json' => ['nullable', 'string', 'max:5242880'],
        ];
    }

    /**
     * The field the import came through, so validation errors land on the
     * input the user actually used (the file picker or the paste box).
     */
    public function importField(): string
    {
        return $this->hasFile('file') ? 'file' : 'json';
    }

    /**
     * Decode and normalize the imported JSON into the shape the import
     * command expects. Accepts either an uploaded file or a pasted string.
     * The domain value objects own deep structural validation, so this only
     * guards the envelope.
     *
     * @return array{name: string, content: array<string, mixed>, talk_settings: array<string, mixed>, flow: array<string, mixed>|null}
     */
    public function presentationPayload(): array
    {
        $field = $this->importField();

        $raw = $this->hasFile('file')
            ? (string) $this->file('file')->get()
            : trim((string) $this->input('json', ''));

        if ($raw === '') {
            throw ValidationException::withMessages([
                $field => 'Provide a JSON file or paste JSON to import.',
            ]);
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                $field => 'The import does not contain valid JSON.',
            ]);
        }

        if (! is_array($decoded['content'] ?? null)) {
            throw ValidationException::withMessages([
                $field => 'The presentation JSON is missing its "content" object.',
            ]);
        }

        $name = trim((string) ($decoded['name'] ?? ''));

        return [
            'name' => mb_substr($name === '' ? 'Imported presentation' : $name, 0, 255),
            'content' => $decoded['content'],
            'talk_settings' => is_array($decoded['talk_settings'] ?? null) ? $decoded['talk_settings'] : [],
            'flow' => is_array($decoded['flow'] ?? null) ? $decoded['flow'] : null,
        ];
    }
}
