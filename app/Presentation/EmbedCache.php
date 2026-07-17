<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Domain\Presentation\ValueObjects\PresentationContent;

/**
 * File cache for embeddable web-component builds, keyed by the presentation's
 * embed token. Generation is lazy: the embed endpoint materializes the file on
 * first access, and saves refresh it only if it already exists.
 */
class EmbedCache
{
    public function __construct(private readonly PresenterFactory $presenters) {}

    /**
     * @return string|null Absolute path to the cached build, or null if not generated yet.
     */
    public function find(string $token): ?string
    {
        $path = $this->path($token);

        return file_exists($path) ? $path : null;
    }

    /**
     * @return string Absolute path to the freshly generated build.
     */
    public function store(string $token, PresentationContent $content): string
    {
        $output = $this->presenters
            ->make(ExportFormat::WebComponent, $this->customElementTag($token))
            ->present($content, 'embed');

        $path = $this->path($token);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        // Write-then-rename so concurrent readers never see a partial file.
        $temporary = $path.'.'.uniqid('tmp', true);
        file_put_contents($temporary, $output->content);
        rename($temporary, $path);

        return $path;
    }

    /**
     * Regenerate the cached build, but only if one was already materialized.
     */
    public function refresh(string $token, PresentationContent $content): void
    {
        if ($this->find($token) !== null) {
            $this->store($token, $content);
        }
    }

    /**
     * Unique per presentation so two embedded decks on one page don't collide
     * on customElements.define(). Custom element tags must be lowercase.
     */
    public function customElementTag(string $token): string
    {
        return 'lecturn-deck-'.strtolower(substr($token, 0, 8));
    }

    private function path(string $token): string
    {
        return storage_path("app/embeds/{$token}.js");
    }
}
