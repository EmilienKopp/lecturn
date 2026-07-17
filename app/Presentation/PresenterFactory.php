<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\Contracts\Presenter;
use App\Presentation\Presenters\NodePresenter;

class PresenterFactory
{
    public function make(ExportFormat $format): Presenter
    {
        return match ($format) {
            ExportFormat::SvelteSource,
            ExportFormat::WebComponent => new NodePresenter($format),
        };
    }
}
