<?php

declare(strict_types=1);

namespace App\Domain\Presentation\Contracts;

use App\Domain\Presentation\Entities\PresentationEntity;

interface PresentationRepository
{
    public function findById(int $id): PresentationEntity;

    public function save(PresentationEntity $presentation): PresentationEntity;

    public function delete(int $id): void;

    /**
     * Stores the deck-wide background image and returns its public URL.
     */
    public function storeBackgroundImage(int $id, string $filePath, string $fileName): string;

    public function clearBackgroundImage(int $id): void;

    /**
     * Stores a content image (used by blocks) and returns its public URL.
     */
    public function storeImage(int $id, string $filePath, string $fileName): string;

    /**
     * Downloads a remote image and stores it as a content image, returning its
     * new public URL. Throws if the URL can't be fetched or isn't an image.
     */
    public function storeImageFromUrl(int $id, string $url): string;

    /**
     * Removes every content image attached to the presentation.
     */
    public function clearImages(int $id): void;
}
