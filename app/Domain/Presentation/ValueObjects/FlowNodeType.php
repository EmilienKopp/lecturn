<?php

declare(strict_types=1);

namespace App\Domain\Presentation\ValueObjects;

enum FlowNodeType: string
{
    case Slide = 'slide';
    case Transition = 'transition';
    case CodeAction = 'code-action';
}
