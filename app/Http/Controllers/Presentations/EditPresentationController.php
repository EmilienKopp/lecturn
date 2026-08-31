<?php

namespace App\Http\Controllers\Presentations;

use App\Http\Controllers\Controller;
use App\Infrastructure\ReadModels\PresentationReadModel;
use App\Models\PresentationModel;
use App\Models\Team;
use App\Presentation\EmbedCache;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EditPresentationController extends Controller
{
    public function __construct(
        private readonly PresentationReadModel $presentations,
        private readonly EmbedCache $embeds,
    ) {}

    public function __invoke(Team $current_team, PresentationModel $presentation): Response
    {
        Gate::authorize('view', $presentation);

        return Inertia::render('presentations/Editor', [
            'presentation' => $this->presentations->findForEditor($presentation->id),
            'embed' => [
                'url' => route('presentations.embed', ['presentation' => $presentation->embed_token]),
                'tag' => $this->embeds->customElementTag($presentation->embed_token),
            ],
            'viewerUrl' => route('presentations.viewer', ['presentation' => $presentation->embed_token]),
        ]);
    }
}
