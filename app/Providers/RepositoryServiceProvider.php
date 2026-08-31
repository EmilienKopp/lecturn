<?php

namespace App\Providers;

use App\Domain\Presentation\Contracts\PresentationRepository;
use App\Domain\Presentation\Contracts\TranslationServiceContract;
use App\Infrastructure\Adapters\UnconfiguredTranslationService;
use App\Infrastructure\Adapters\YoYoTranslateAdapter;
use App\Infrastructure\Persistence\Repositories\EloquentPresentationRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PresentationRepository::class, EloquentPresentationRepository::class);

        $this->app->bind(TranslationServiceContract::class, function () {
            $apiKey = (string) config('yoyotranslate.api_key');

            if ($apiKey === '') {
                return new UnconfiguredTranslationService;
            }

            $http = Http::baseUrl((string) config('yoyotranslate.api_base_url'))
                ->withToken($apiKey)
                ->acceptJson()
                ->asJson();

            return new YoYoTranslateAdapter($http);
        });
    }
}
