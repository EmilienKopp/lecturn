<?php

declare(strict_types=1);

namespace App\Http\Controllers\Presentations;

use App\Application\Actions\Presentations\RecordReactions;
use App\Application\Commands\RecordReactionsCommand;
use App\Http\Controllers\Controller;
use App\Http\Requests\Presentations\RecordReactionsRequest;
use App\Models\PresentationModel;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class RecordReactionsController extends Controller
{
    public function __construct(private readonly RecordReactions $recordReactions) {}

    public function __invoke(RecordReactionsRequest $request, PresentationModel $presentation): Response
    {
        $this->recordReactions->execute(new RecordReactionsCommand(
            embedToken: $presentation->embed_token,
            viewerId: (string) $request->validated('viewerId'),
            counts: $request->reactionCounts(),
            leaving: (bool) $request->validated('leaving', false),
            at: Carbon::now(),
        ));

        return response()->noContent();
    }
}
