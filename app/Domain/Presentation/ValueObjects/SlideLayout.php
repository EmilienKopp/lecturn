<?php

declare(strict_types=1);

namespace App\Domain\Presentation\ValueObjects;

enum SlideLayout: string
{
    case Full = 'full';
    case Center = 'center';
    case TopMain = 'top-main';
    case TopMainFooter = 'top-main-footer';
    case LeftRight = 'left-right';
    case LeftWideRight = 'left-wide-right';
    case Grid2x2 = 'grid-2x2';
    case Grid2x3 = 'grid-2x3';
    case CustomGrid = 'custom-grid';
    case RichText = 'rich-text';

    /**
     * @return list<string>
     */
    public function slots(): array
    {
        return match ($this) {
            self::Full, self::Center, self::CustomGrid, self::RichText => ['main'],
            self::TopMain => ['top', 'main'],
            self::TopMainFooter => ['top', 'main', 'footer'],
            self::LeftRight, self::LeftWideRight => ['left', 'right'],
            self::Grid2x2 => ['a', 'b', 'c', 'd'],
            self::Grid2x3 => ['a', 'b', 'c', 'd', 'e', 'f'],
        };
    }
}
