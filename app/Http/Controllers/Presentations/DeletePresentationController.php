<?php

namespace App\Http\Controllers\Presentations;

use App\Application\Actions\Presentations\DeletePresentation;
use App\Application\Commands\DeletePresentationCommand;
use App\Http\Controllers\Controller;
use App\Models\PresentationModel;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DeletePresentationController extends Controller
{
    public function __construct(private readonly DeletePresentation $deletePresentation) {}

    public function __invoke(Team $current_team, PresentationModel $presentation): RedirectResponse
    {
        Gate::authorize('delete', $presentation);

        $this->deletePresentation->execute(
            new DeletePresentationCommand(presentation_id: $presentation->id),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Presentation deleted.')]);

        return redirect()->route('presentations.index', ['current_team' => $current_team->slug]);
    }
}
