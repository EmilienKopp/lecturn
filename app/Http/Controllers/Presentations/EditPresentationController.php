<?php

namespace App\Http\Controllers\Presentations;

use App\Http\Controllers\Controller;
use App\Infrastructure\ReadModels\PresentationReadModel;
use App\Models\PresentationModel;
use App\Models\Team;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EditPresentationController extends Controller
{
    public function __construct(private readonly PresentationReadModel $presentations) {}

    public function __invoke(Team $current_team, PresentationModel $presentation): Response
    {
        Gate::authorize('view', $presentation);

        return Inertia::render('presentations/Editor', [
            'presentation' => $this->presentations->findForEditor($presentation->id),
        ]);
    }
}
