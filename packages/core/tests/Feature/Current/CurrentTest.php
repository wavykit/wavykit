<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use WavyKit\Current as CurrentReader;
use WavyKit\Facades\Current;
use WavyKit\Navigation\MenuRegistry;
use WavyKit\Pages\Page;
use WavyKit\Pages\PageRegistry;
use WavyKit\Pages\Pages;

beforeEach(function () {
    Route::get('/projects', fn () => 'projects')->name('projects.index');
    Route::get('/projects/archive', fn () => [
        'page' => Current::page()?->id,
        'pageId' => Current::pageId(),
        'is' => [
            'exact' => Current::is('projects.archive'),
            'wildcard' => Current::is('projects.*'),
            'any' => Current::is('settings', 'projects.archive'),
            'ancestor' => Current::is('projects'),
            'none' => Current::is(),
        ],
        'breadcrumbs' => Current::breadcrumbs(),
        'menu' => Current::menu('main'),
        'contextPage' => Current::context()->page?->id,
        'user' => Current::user()?->getAuthIdentifier(),
    ])->name('projects.archive');
    Route::get('/unregistered', fn () => [
        'pageId' => Current::pageId(),
        'is' => Current::is('*'),
        'breadcrumbs' => Current::breadcrumbs(),
    ])->name('unregistered');

    app(PageRegistry::class)->register(
        Page::make('projects')->route('projects.index')->label('Projects')->children(
            Page::make('projects.archive')->route('projects.archive')->label('Archive'),
        ),
    );
    app(MenuRegistry::class)->derive('main', 'projects');
});

test('the facade reads the same values as the readers', function () {
    $this->app['router']->matched(function ($event) {
        $event->request->setUserResolver(fn () => new GenericUser(['id' => 'alice']));
    });

    $response = $this->getJson('/projects/archive')->assertOk()
        ->assertJsonPath('page', 'projects.archive')
        ->assertJsonPath('pageId', 'projects.archive')
        ->assertJsonPath('is', ['exact' => true, 'wildcard' => true, 'any' => true, 'ancestor' => false, 'none' => false])
        ->assertJsonPath('contextPage', 'projects.archive')
        ->assertJsonPath('user', 'alice');

    expect(array_column($response->json('breadcrumbs'), 'pageId'))->toBe(['projects', 'projects.archive'])
        ->and($response->json('menu.0.pageId'))->toBe('projects')
        ->and($response->json('menu.0.active'))->toBeTrue()
        ->and($response->json('menu.0.children.0.current'))->toBeTrue();
});

test('the facade reports no page on unregistered routes', function () {
    $this->getJson('/unregistered')->assertOk()
        ->assertJsonPath('pageId', null)
        ->assertJsonPath('is', false)
        ->assertJsonPath('breadcrumbs', []);
});

test('the injectable reader and the facade share one stateless instance', function () {
    expect(app(CurrentReader::class))->toBe(app(CurrentReader::class))
        ->and(Current::getFacadeRoot())->toBe(app(CurrentReader::class));
});

test('the facade resolves request-scoped readers again in each scope', function () {
    $built = 0;
    $this->app->resolving(Pages::class, function () use (&$built) {
        $built++;
    });
    Current::pageId();
    Current::pageId();
    expect($built)->toBe(1);

    // Queue workers and Octane forget scoped instances between jobs and requests.
    $this->app->forgetScopedInstances();
    Current::pageId();
    expect($built)->toBe(2);
});
