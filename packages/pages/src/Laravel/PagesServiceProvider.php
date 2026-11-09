<?php

declare(strict_types=1);

namespace WavyKit\Pages\Laravel;

use Illuminate\Support\ServiceProvider;
use WavyKit\Pages\PageRegistry;

final class PagesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PageRegistry::class);
        $this->app->scoped(Pages::class);
    }
}
