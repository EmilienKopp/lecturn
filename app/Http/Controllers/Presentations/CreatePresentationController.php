<?php

namespace App\Http\Controllers\Presentations;

use App\Application\Actions\Presentations\CreatePresentation;
use App\Application\Commands\CreatePresentationCommand;
use App\Http\Controllers\Controller;
use App\Http\Requests\Presentations\CreatePresentationRequest;
use App\Models\PresentationModel;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CreatePresentationController extends Controller
{
    public function __construct(private readonly CreatePresentation $createPresentation) {}

    public function __invoke(CreatePresentationRequest $request, Team $current_team): RedirectResponse
    {
        Gate::authorize('create', [PresentationModel::class, $current_team]);

        $presentation = $this->createPresentation->execute(
            new CreatePresentationCommand(
                team_id: $current_team->id,
                name: $request->validated('name'),
            ),
        );

        return redirect()->route('presentations.edit', [
            'current_team' => $current_team->slug,
            'presentation' => $presentation->id,
        ]);
    }
}
