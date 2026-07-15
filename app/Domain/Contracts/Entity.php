<?php

declare(strict_types=1);

namespace App\Domain\Contracts;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use IteratorAggregate;

/**
 * @extends Arrayable<string, mixed>
 * @extends ArrayAccess<string, mixed>
 * @extends IteratorAggregate<string, mixed>
 */
interface Entity extends Arrayable, ArrayAccess, IteratorAggregate {}
