<?php

namespace App\Http\Controllers\Presentations;

use App\Application\Actions\Presentations\UploadPresentationBackground;
use App\Application\Commands\UploadPresentationBackgroundCommand;
use App\Http\Controllers\Controller;
use App\Http\Requests\Presentations\UploadPresentationBackgroundRequest;
use App\Models\PresentationModel;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class UploadPresentationBackgroundController extends Controller
{
    public function __construct(private readonly UploadPresentationBackground $uploadBackground) {}

    public function __invoke(
        UploadPresentationBackgroundRequest $request,
        Team $current_team,
        PresentationModel $presentation,
    ): JsonResponse {
        Gate::authorize('update', $presentation);

        $file = $request->file('image');

        $url = $this->uploadBackground->execute(
            new UploadPresentationBackgroundCommand(
                presentation_id: $presentation->id,
                filePath: $file->getRealPath(),
                fileName: 'background.'.$file->getClientOriginalExtension(),
            ),
        );

        return response()->json(['url' => $url]);
    }
}
