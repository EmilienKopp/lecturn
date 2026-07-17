<?php

namespace App\Http\Controllers\Presentations;

use App\Domain\Presentation\ValueObjects\PresentationContent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Presentations\ExportPresentationRequest;
use App\Infrastructure\ReadModels\PresentationReadModel;
use App\Models\PresentationModel;
use App\Models\Team;
use App\Presentation\PresenterFactory;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportPresentationController extends Controller
{
    public function __construct(
        private readonly PresentationReadModel $presentations,
        private readonly PresenterFactory $presenters,
    ) {}

    public function __invoke(
        ExportPresentationRequest $request,
        Team $current_team,
        PresentationModel $presentation,
    ): StreamedResponse {
        Gate::authorize('view', $presentation);

        $data = $this->presentations->findForEditor($presentation->id);

        $output = $this->presenters
            ->make($request->exportFormat())
            ->present(PresentationContent::fromArray($data['content']), $data['name']);

        return response()->streamDownload(
            fn () => print $output->content,
            $output->filename,
            ['Content-Type' => $output->mimeType],
        );
    }
}
