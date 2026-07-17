<?php

declare(strict_types=1);

namespace App\Presentation;

enum ExportFormat: string
{
    case SvelteSource = 'svelte';
    case WebComponent = 'web-component';

    public function mimeType(): string
    {
        return match ($this) {
            self::SvelteSource => 'text/plain',
            self::WebComponent => 'text/javascript',
        };
    }

    public function extension(): string
    {
        return match ($this) {
            self::SvelteSource => 'svelte',
            self::WebComponent => 'js',
        };
    }
}
