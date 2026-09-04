<?php

declare(strict_types=1);

namespace App\Http\Controllers\Presentations;

use App\Application\Actions\Presentations\StartSession;
use App\Application\Commands\StartSessionCommand;
use App\Http\Controllers\Controller;
use App\Models\PresentationModel;
use App\Models\Team;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class StartSessionController extends Controller
{
    public function __construct(private readonly StartSession $startSession) {}

    public function __invoke(Team $current_team, PresentationModel $presentation): Response
    {
        Gate::authorize('view', $presentation);

        $this->startSession->execute(new StartSessionCommand(
            presentationId: $presentation->id,
            teamId: $presentation->team_id,
            startedAt: Carbon::now(),
        ));

        return response()->noContent();
    }
}
