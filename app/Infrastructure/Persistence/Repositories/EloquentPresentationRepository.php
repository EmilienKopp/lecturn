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
}
