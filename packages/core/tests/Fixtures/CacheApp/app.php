<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use WavyKit\CoreServiceProvider;
use WavyKit\Tests\Fixtures\CacheApp\FixtureController;
use WavyKit\Tests\Fixtures\CacheApp\FixtureProvider;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([CoreServiceProvider::class, FixtureProvider::class])
    ->withRouting(using: function (): void {
        Route::get('/projects', [FixtureController::class, 'index'])->name('projects.index');
        Route::get('/projects/{project}', [FixtureController::class, 'show'])->name('projects.show');
        Route::get('/projects/{project}/edit/{tab?}', [FixtureController::class, 'show'])
            ->middleware(SubstituteBindings::class)->name('projects.edit');
    })
    ->create();
