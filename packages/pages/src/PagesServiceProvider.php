<?php

declare(strict_types=1);

namespace WavyKit\Pages;

use Illuminate\Support\ServiceProvider;

/** Shares declarations while scoping the request resolver to the application request/job lifecycle. */
final class PagesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PageRegistry::class);
        $this->app->scoped(Pages::class);
    }
}
