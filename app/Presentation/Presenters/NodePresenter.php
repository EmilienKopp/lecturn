<?php

declare(strict_types=1);

namespace App\Presentation\Presenters;

use App\Domain\Presentation\ValueObjects\FlowGraph;
use App\Domain\Presentation\ValueObjects\PresentationContent;
use App\Presentation\Contracts\Presenter;
use App\Presentation\ExportFormat;
use App\Presentation\PresenterOutput;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Presents a presentation by delegating to scripts/present.mjs, so the
 * Svelte generation logic lives in exactly one place (codegen.ts) and is
 * shared with the frontend editor.
 */
class NodePresenter implements Presenter
{
    public function __construct(
        private readonly ExportFormat $format,
        private readonly ?string $customElementTag = null,
    ) {}

    public function present(PresentationContent $content, string $name, ?FlowGraph $flow = null): PresenterOutput
    {
        $process = new Process(['node', base_path('scripts/present.mjs')], base_path());
        $process->setInput(json_encode([
            'format' => $this->format->value,
            'content' => $content->toArray(),
            'flow' => $flow?->toArray(),
            'tag' => $this->customElementTag,
        ], JSON_THROW_ON_ERROR));
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                "Presentation export ({$this->format->value}) failed: ".$process->getErrorOutput(),
            );
        }

        $slug = Str::slug($name) ?: 'presentation';

        return new PresenterOutput(
            content: $process->getOutput(),
            mimeType: $this->format->mimeType(),
            filename: "{$slug}.{$this->format->extension()}",
        );
    }
}
