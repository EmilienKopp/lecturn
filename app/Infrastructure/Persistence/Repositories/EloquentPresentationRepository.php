<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Presentation\Contracts\PresentationRepository;
use App\Domain\Presentation\Entities\PresentationEntity;
use App\Models\PresentationModel;

class EloquentPresentationRepository implements PresentationRepository
{
    public function findById(int $id): PresentationEntity
    {
        return PresentationModel::findOrFail($id)->toEntity();
    }

    public function save(PresentationEntity $presentation): PresentationEntity
    {
        $attributes = [
            'team_id' => $presentation->team_id,
            'name' => $presentation->name,
            'content' => $presentation->content->toArray(),
            'talk_settings' => $presentation->talkSettings->toArray(),
            'flow' => $presentation->flow?->toArray(),
        ];

        if ($presentation->id === null) {
            $model = PresentationModel::create($attributes);
        } else {
            $model = PresentationModel::findOrFail($presentation->id);
            $model->update($attributes);
        }

        return $model->refresh()->toEntity();
    }

    public function delete(int $id): void
    {
        PresentationModel::whereKey($id)->delete();
    }

    public function storeBackgroundImage(int $id, string $filePath, string $fileName): string
    {
        $model = PresentationModel::findOrFail($id);

        $media = $model->addMedia($filePath)
            ->usingFileName($fileName)
            ->toMediaCollection(PresentationModel::BACKGROUND_COLLECTION);

        return $media->getFullUrl();
    }

    public function clearBackgroundImage(int $id): void
    {
        PresentationModel::findOrFail($id)
            ->clearMediaCollection(PresentationModel::BACKGROUND_COLLECTION);
    }

    public function storeImage(int $id, string $filePath, string $fileName): string
    {
        $model = PresentationModel::findOrFail($id);

        $media = $model->addMedia($filePath)
            ->usingFileName($fileName)
            ->toMediaCollection(PresentationModel::IMAGES_COLLECTION);

        return $media->getFullUrl();
    }
}
