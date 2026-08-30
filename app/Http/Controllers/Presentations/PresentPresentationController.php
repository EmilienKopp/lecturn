<?php

namespace App\Http\Controllers\Presentations;

use App\Http\Controllers\Controller;
use App\Infrastructure\ReadModels\PresentationReadModel;
use App\Models\PresentationModel;
use App\Models\Team;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PresentPresentationController extends Controller
{
    public function __construct(private readonly PresentationReadModel $presentations) {}

    public function __invoke(Team $current_team, PresentationModel $presentation): Response
    {
        Gate::authorize('view', $presentation);

        $data = $this->presentations->findForPresent($presentation->id);

        return Inertia::render('presentations/Present', [
            'presentation' => $data,
            'viewerUrl' => route('presentations.viewer', ['presentation' => $presentation->embed_token]),
        ]);
    }
}
