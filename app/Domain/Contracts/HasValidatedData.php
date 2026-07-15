<?php

declare(strict_types=1);

namespace App\Domain\Contracts;

interface HasValidatedData
{
    /** @return array<string, mixed> */
    public function validated(string|null $key = null, mixed $default = null): array|string|null;
}
