<?php

namespace App\Providers;

use App\Providers\MetaCloudProvider;
use App\Providers\WhatsAppProvider;
use App\Services\CatalogClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WhatsAppProvider::class, fn () => new MetaCloudProvider);
        $this->app->singleton(CatalogClient::class, fn () => new CatalogClient);
    }

    public function boot(): void
    {
        //
    }
}
