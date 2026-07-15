<?php

namespace App\Providers;

use App\Domain\Presentation\Contracts\PresentationRepository;
use App\Infrastructure\Persistence\Repositories\EloquentPresentationRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PresentationRepository::class, EloquentPresentationRepository::class);
    }
}
