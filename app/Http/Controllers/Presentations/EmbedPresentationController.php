<?php

namespace App\Http\Controllers\Presentations;

use App\Domain\Presentation\ValueObjects\FlowGraph;
use App\Domain\Presentation\ValueObjects\PresentationContent;
use App\Http\Controllers\Controller;
use App\Infrastructure\ReadModels\PresentationReadModel;
use App\Models\PresentationModel;
use App\Presentation\EmbedCache;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Public, unauthenticated endpoint — the embed script is loaded via
 * <script src> from external sites, keyed by the unguessable embed token.
 * The build is materialized lazily on first access and served from disk
 * afterwards; saves refresh it via RefreshPresentationEmbed.
 */
class EmbedPresentationController extends Controller
{
    public function __construct(
        private readonly PresentationReadModel $presentations,
        private readonly EmbedCache $embeds,
    ) {}

    public function __invoke(PresentationModel $presentation): BinaryFileResponse
    {
        $path = $this->embeds->find($presentation->embed_token);

        if ($path === null) {
            $data = $this->presentations->findForEmbed($presentation->id);

            $path = $this->embeds->store(
                $presentation->embed_token,
                PresentationContent::fromArray($data['content']),
                $data['flow'] !== null ? FlowGraph::fromArray($data['flow']) : null,
            );
        }

        return response()->file($path, [
            'Content-Type' => 'text/javascript',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
