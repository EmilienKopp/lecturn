<?php

namespace App\Http\Controllers\Presentations;

use App\Application\Actions\Presentations\RemovePresentationBackground;
use App\Http\Controllers\Controller;
use App\Models\PresentationModel;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DeletePresentationBackgroundController extends Controller
{
    public function __construct(private readonly RemovePresentationBackground $removeBackground) {}

    public function __invoke(
        Team $current_team,
        PresentationModel $presentation,
    ): JsonResponse {
        Gate::authorize('update', $presentation);

        $this->removeBackground->execute($presentation->id);

        return response()->json(['url' => null]);
    }
}
