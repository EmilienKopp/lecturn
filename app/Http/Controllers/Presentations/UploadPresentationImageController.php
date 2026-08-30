<?php

namespace App\Http\Controllers\Presentations;

use App\Application\Actions\Presentations\UploadPresentationImage;
use App\Application\Commands\UploadPresentationImageCommand;
use App\Http\Controllers\Controller;
use App\Http\Requests\Presentations\UploadPresentationImageRequest;
use App\Models\PresentationModel;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class UploadPresentationImageController extends Controller
{
    public function __construct(private readonly UploadPresentationImage $uploadImage) {}

    public function __invoke(
        UploadPresentationImageRequest $request,
        Team $current_team,
        PresentationModel $presentation,
    ): JsonResponse {
        Gate::authorize('update', $presentation);

        $file = $request->file('image');

        $url = $this->uploadImage->execute(
            new UploadPresentationImageCommand(
                presentation_id: $presentation->id,
                filePath: $file->getRealPath(),
                fileName: $file->getClientOriginalName(),
            ),
        );

        return response()->json(['url' => $url]);
    }
}
