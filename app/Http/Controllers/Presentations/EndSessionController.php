<?php

declare(strict_types=1);

namespace App\Http\Controllers\Presentations;

use App\Application\Actions\Presentations\EndSession;
use App\Application\Commands\EndSessionCommand;
use App\Http\Controllers\Controller;
use App\Models\PresentationModel;
use App\Models\Team;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class EndSessionController extends Controller
{
    public function __construct(private readonly EndSession $endSession) {}

    public function __invoke(Team $current_team, PresentationModel $presentation): Response
    {
        Gate::authorize('view', $presentation);

        $this->endSession->execute(new EndSessionCommand(
            presentationId: $presentation->id,
            endedAt: Carbon::now(),
        ));

        return response()->noContent();
    }
}
