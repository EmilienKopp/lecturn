<?php

declare(strict_types=1);

namespace App\Http\Controllers\Presentations;

use App\Events\Presentations\ReactionSent;
use App\Http\Controllers\Controller;
use App\Models\PresentationModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SendReactionController extends Controller
{
    /** @var list<string> */
    private const array ALLOWED_EMOJIS = ['👏', '❤️', '😂', '🤯', '🙌', '🔥'];

    public function __invoke(Request $request, PresentationModel $presentation): Response
    {
        $validated = $request->validate([
            'emoji' => ['required', 'string', 'in:'.implode(',', self::ALLOWED_EMOJIS)],
        ]);

        ReactionSent::dispatch($presentation->embed_token, $validated['emoji']);

        return response()->noContent();
    }
}
