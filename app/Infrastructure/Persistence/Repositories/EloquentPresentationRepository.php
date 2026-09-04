<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Presentation\Contracts\PresentationRepository;
use App\Domain\Presentation\Entities\PresentationEntity;
use App\Models\PresentationModel;
use Illuminate\Support\Facades\Http;
use RuntimeException;

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
            'yoyotranslate_session_id' => $presentation->yoyotranslateSessionId,
            'yoyotranslate_session_started_at' => $presentation->yoyotranslateSessionStartedAt,
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

    public function storeImageFromUrl(int $id, string $url): string
    {
        $model = PresentationModel::findOrFail($id);

        $response = Http::connectTimeout(10)->timeout(20)->get($url);

        if ($response->failed()) {
            throw new RuntimeException("Failed to download image from {$url} (status {$response->status()}).");
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'lecturn-import-');
        file_put_contents($temporaryPath, $response->body());

        try {
            // The collection's accepted mime types reject anything that isn't an image.
            $media = $model->addMedia($temporaryPath)
                ->usingFileName($this->fileNameForUrl($url))
                ->toMediaCollection(PresentationModel::IMAGES_COLLECTION);
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }

        return $media->getFullUrl();
    }

    public function clearImages(int $id): void
    {
        PresentationModel::findOrFail($id)
            ->clearMediaCollection(PresentationModel::IMAGES_COLLECTION);
    }

    private function fileNameForUrl(string $url): string
    {
        $name = urldecode(basename((string) parse_url($url, PHP_URL_PATH)));

        return $name !== '' ? $name : 'image';
    }
}
