<?php

declare(strict_types=1);

namespace App\Presentation\Contracts;

use App\Domain\Presentation\ValueObjects\PresentationContent;
use App\Presentation\PresenterOutput;

interface Presenter
{
    public function present(PresentationContent $content, string $name): PresenterOutput;
}
