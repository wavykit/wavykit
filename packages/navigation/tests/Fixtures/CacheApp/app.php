<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use WavyKit\Navigation\NavigationServiceProvider;
use WavyKit\Navigation\Tests\Fixtures\CacheApp\FixtureController;
use WavyKit\Navigation\Tests\Fixtures\CacheApp\FixtureProvider;
use WavyKit\Pages\PagesServiceProvider;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([PagesServiceProvider::class, NavigationServiceProvider::class, FixtureProvider::class])
    ->withRouting(using: function (): void {
        Route::get('/projects', [FixtureController::class, 'index'])->name('projects.index');
        Route::get('/projects/{project}', [FixtureController::class, 'show'])->name('projects.show');
        Route::get('/projects/{project}/edit/{tab?}', [FixtureController::class, 'show'])
            ->middleware(SubstituteBindings::class)->name('projects.edit');
    })
    ->create();
