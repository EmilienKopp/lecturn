<?php

declare(strict_types=1);

namespace App\Domain\Presentation\Contracts;

use App\Domain\Presentation\Entities\PresentationEntity;

interface PresentationRepository
{
    public function findById(int $id): PresentationEntity;

    public function save(PresentationEntity $presentation): PresentationEntity;

    public function delete(int $id): void;
}
