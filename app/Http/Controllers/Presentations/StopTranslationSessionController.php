<?php

namespace App\Http\Controllers\Presentations;

use App\Application\Actions\Presentations\StopTranslationSession;
use App\Application\Commands\StopTranslationSessionCommand;
use App\Http\Controllers\Controller;
use App\Models\PresentationModel;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class StopTranslationSessionController extends Controller
{
    public function __construct(private readonly StopTranslationSession $stopTranslationSession) {}

    public function __invoke(Team $current_team, PresentationModel $presentation): RedirectResponse
    {
        Gate::authorize('update', $presentation);

        $this->stopTranslationSession->execute(
            new StopTranslationSessionCommand(
                presentationId: $presentation->id,
                userId: Auth::id(),
            ),
        );

        return redirect()->route('presentations.present', [
            'current_team' => $current_team->slug,
            'presentation' => $presentation->id,
        ]);
    }
}
