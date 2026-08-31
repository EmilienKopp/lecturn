<?php

namespace App\Http\Controllers\Presentations;

use App\Application\Actions\Presentations\StartTranslationSession;
use App\Application\Commands\StartTranslationSessionCommand;
use App\Http\Controllers\Controller;
use App\Http\Requests\Presentations\StartTranslationSessionRequest;
use App\Models\PresentationModel;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class StartTranslationSessionController extends Controller
{
    public function __construct(private readonly StartTranslationSession $startTranslationSession) {}

    public function __invoke(StartTranslationSessionRequest $request, Team $current_team, PresentationModel $presentation): RedirectResponse
    {
        Gate::authorize('update', $presentation);

        $this->startTranslationSession->execute(
            new StartTranslationSessionCommand(
                presentationId: $presentation->id,
                userId: Auth::id(),
                sourceLanguage: $request->validated('source_language'),
                eventId: $request->eventId(),
            ),
        );

        return redirect()->route('presentations.present', [
            'current_team' => $current_team->slug,
            'presentation' => $presentation->id,
        ]);
    }
}
