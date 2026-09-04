<?php

namespace App\Http\Controllers\Presentations;

use App\Application\Sequences\Presentations\ImportPresentationPayload;
use App\Application\Sequences\Presentations\ImportPresentationSequence;
use App\Domain\Presentation\Entities\PresentationEntity;
use App\Domain\Presentation\Exceptions\InvalidFlowGraph;
use App\Domain\Presentation\Exceptions\InvalidPresentationContent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Presentations\ImportPresentationRequest;
use App\Models\PresentationModel;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ImportPresentationController extends Controller
{
    public function __construct(private readonly ImportPresentationSequence $import) {}

    public function __invoke(ImportPresentationRequest $request, Team $current_team): RedirectResponse
    {
        Gate::authorize('create', [PresentationModel::class, $current_team]);

        $payload = $request->presentationPayload();

        try {
            $result = $this->import->import(
                new ImportPresentationPayload(
                    team_id: $current_team->id,
                    name: $payload['name'],
                    content: $payload['content'],
                    talkSettings: $payload['talk_settings'],
                    flow: $payload['flow'],
                ),
            );
        } catch (InvalidPresentationContent|InvalidFlowGraph $exception) {
            throw ValidationException::withMessages([
                $request->importField() => $exception->getMessage(),
            ]);
        }

        /** @var PresentationEntity $presentation */
        $presentation = $result->get('presentation');

        $this->flashImageWarning($result->get('unresolvedImages', []));

        return redirect()->route('presentations.edit', [
            'current_team' => $current_team->slug,
            'presentation' => $presentation->id,
        ]);
    }

    /**
     * @param  list<string>  $unresolvedImages
     */
    private function flashImageWarning(array $unresolvedImages): void
    {
        if ($unresolvedImages === []) {
            return;
        }

        Inertia::flash('toast', [
            'type' => 'warning',
            'message' => trans_choice(
                '{1} :count image could not be re-hosted. If the source asset is not public or gets removed by its owner, it will stop displaying.'
                    .'|[2,*] :count images could not be re-hosted. If the source assets are not public or get removed by their owner, they will stop displaying.',
                count($unresolvedImages),
                ['count' => count($unresolvedImages)],
            ),
        ]);
    }
}
