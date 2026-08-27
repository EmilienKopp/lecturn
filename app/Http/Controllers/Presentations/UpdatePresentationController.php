<?php

namespace App\Http\Controllers\Presentations;

use App\Application\Actions\Presentations\UpdatePresentation;
use App\Application\Commands\UpdatePresentationCommand;
use App\Domain\Presentation\Exceptions\InvalidPresentationContent;
use App\Domain\Presentation\ValueObjects\PresentationContent;
use App\Domain\Presentation\ValueObjects\TalkSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Presentations\UpdatePresentationRequest;
use App\Models\PresentationModel;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class UpdatePresentationController extends Controller
{
    public function __construct(private readonly UpdatePresentation $updatePresentation) {}

    public function __invoke(
        UpdatePresentationRequest $request,
        Team $current_team,
        PresentationModel $presentation,
    ): RedirectResponse {
        Gate::authorize('update', $presentation);

        try {
            $content = $request->has('content')
                ? PresentationContent::fromArray($request->validated('content'))
                : null;
        } catch (InvalidPresentationContent $exception) {
            throw ValidationException::withMessages(['content' => $exception->getMessage()]);
        }

        $talkSettings = $request->has('talk_settings')
            ? TalkSettings::fromArray($request->validated('talk_settings'))
            : null;

        $this->updatePresentation->execute(
            new UpdatePresentationCommand(
                presentation_id: $presentation->id,
                name: $request->validated('name', null),
                content: $content,
                talkSettings: $talkSettings,
            ),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Presentation saved.')]);

        return back();
    }
}
