<?php

declare(strict_types=1);

namespace App\Domain\Contracts;

use ArrayAccess;
use Spatie\LaravelData\Contracts\Data;

/**
 * @extends ArrayAccess<int|string, mixed>
 */
interface DTO extends Data
{
    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): static;

    /** @return array<string, mixed> */
    public function toArray(): array;
}
