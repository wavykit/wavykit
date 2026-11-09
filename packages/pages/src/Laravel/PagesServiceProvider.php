<?php

declare(strict_types=1);

namespace WavyKit\Pages\Laravel;

use Illuminate\Support\ServiceProvider;
use WavyKit\Pages\PageRegistry;

/** Shares declarations while scoping the Laravel resolver to the application request/job lifecycle. */
final class PagesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PageRegistry::class);
        $this->app->scoped(Pages::class);
    }
}
