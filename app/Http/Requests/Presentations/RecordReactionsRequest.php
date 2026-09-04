<?php

declare(strict_types=1);

namespace App\Http\Requests\Presentations;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecordReactionsRequest extends FormRequest
{
    /** @var list<string> */
    public const array ALLOWED_EMOJIS = ['👏', '❤️', '😂', '🤯', '🙌', '🔥'];

    /** Upper bound per emoji in a single flush — guards against tampering. */
    private const int MAX_PER_EMOJI = 500;

    public function authorize(): bool
    {
        // Anonymous audience endpoint — access is gated by the embed token in
        // the route, matching SendReactionController.
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string|Closure>
     */
    public function rules(): array
    {
        return [
            'viewerId' => ['required', 'string', 'max:64'],
            'leaving' => ['sometimes', 'boolean'],
            'counts' => ['sometimes', 'array', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_array($value)) {
                    return;
                }

                foreach (array_keys($value) as $emoji) {
                    if (! in_array($emoji, self::ALLOWED_EMOJIS, true)) {
                        $fail('An unsupported reaction was sent.');

                        return;
                    }
                }
            }],
            'counts.*' => ['integer', 'min:1', 'max:'.self::MAX_PER_EMOJI],
        ];
    }

    /**
     * The reaction tallies keyed by emoji, pruned to the allowed set.
     *
     * @return array<string, int>
     */
    public function reactionCounts(): array
    {
        /** @var array<string, int> $counts */
        $counts = $this->validated('counts', []);

        return $counts;
    }
}
