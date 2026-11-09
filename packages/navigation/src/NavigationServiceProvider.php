<?php

declare(strict_types=1);

namespace WavyKit\Navigation;

use Illuminate\Support\ServiceProvider;

/** Shares menu declarations while scoping the menu resolver to the application request/job lifecycle. */
final class NavigationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MenuRegistry::class);
        $this->app->scoped(Navigation::class);
    }
}
