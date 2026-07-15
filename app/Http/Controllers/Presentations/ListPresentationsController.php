<?php

namespace App\Http\Controllers\Presentations;

use App\Http\Controllers\Controller;
use App\Infrastructure\ReadModels\PresentationReadModel;
use App\Models\Team;
use Inertia\Inertia;
use Inertia\Response;

class ListPresentationsController extends Controller
{
    public function __construct(private readonly PresentationReadModel $presentations) {}

    public function __invoke(Team $current_team): Response
    {
        return Inertia::render('presentations/Index', [
            'presentations' => $this->presentations->listForTeam($current_team->id),
        ]);
    }
}
