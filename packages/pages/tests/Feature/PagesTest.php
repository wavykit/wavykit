<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WavyKit\Pages\Page;
use WavyKit\Pages\PageRegistry;
use WavyKit\Pages\Pages;

beforeEach(function () {
    Route::get('/dashboard', fn () => 'dashboard')->name('dashboard');
    Route::get('/projects', fn () => 'projects')->name('projects.index');
    Route::get('/projects/create', function (Pages $pages) {
        return ['breadcrumbs' => $pages->breadcrumbs(), 'menu' => $pages->navigation('primary')];
    })->name('projects.create');
    Route::get('/other', function (Pages $pages) {
        return ['page' => $pages->page(), 'breadcrumbs' => $pages->breadcrumbs(), 'menu' => $pages->navigation('primary')];
    })->name('other');

    $registry = app(PageRegistry::class);
    $registry->register(
        Page::make('dashboard')->label('Dashboard')->route('dashboard'),
        Page::make('projects')->label('Projects')->route('projects.index')->children(
            Page::make('projects.create')->label('Create project')->route('projects.create'),
        ),
    );
    $registry->deriveMenu('primary', 'dashboard', 'projects');
});

test('existing Laravel routes produce breadcrumbs and a nested active menu', function () {
    $this->getJson('/projects/create')->assertOk()
        ->assertJsonPath('breadcrumbs.0', ['pageId' => 'projects', 'label' => 'Projects', 'url' => 'http://localhost/projects', 'current' => false])
        ->assertJsonPath('breadcrumbs.1.current', true)
        ->assertJsonPath('breadcrumbs.1.url', null)
        ->assertJsonPath('menu.0.active', false)
        ->assertJsonPath('menu.1.current', false)
        ->assertJsonPath('menu.1.active', true)
        ->assertJsonPath('menu.1.children.0.current', true);
});

test('an unregistered route clears active state without removing global navigation', function () {
    $this->getJson('/projects/create')->assertOk()->assertJsonPath('menu.1.active', true);
    $this->getJson('/other')->assertOk()->assertJsonPath('page', null)
        ->assertJsonPath('breadcrumbs', [])
        ->assertJsonPath('menu.1.active', false)
        ->assertJsonPath('menu.1.children.0.current', false);
});

test('a later unmatched request cannot reuse the previous route page context', function () {
    app(ExceptionHandler::class)->renderable(function (NotFoundHttpException $exception) {
        $pages = app(Pages::class);

        return response()->json([
            'page' => $pages->page(),
            'breadcrumbs' => $pages->breadcrumbs(),
            'menu' => $pages->navigation('primary'),
        ], 404);
    });
    $this->getJson('/projects/create')->assertOk();
    $this->getJson('/does-not-exist')->assertNotFound()
        ->assertJsonPath('page', null)
        ->assertJsonPath('breadcrumbs', [])
        ->assertJsonPath('menu.1.active', false);
});

test('route-less ancestors and hidden navigation subtrees retain breadcrumbs', function () {
    app(PageRegistry::class)->register(Page::make('group')->label('Group')->children(
        Page::make('hidden')->route('hidden')->navigationVisible(false)->children(Page::make('leaf')->route('leaf')),
    ));
    app(PageRegistry::class)->deriveMenu('grouped', 'group');
    Route::get('/hidden', fn () => 'hidden')->name('hidden');
    Route::get('/leaf', function (Pages $pages) {
        return ['breadcrumbs' => $pages->breadcrumbs(), 'menu' => $pages->navigation('grouped')];
    })->name('leaf');

    $this->getJson('/leaf')->assertOk()->assertJsonCount(3, 'breadcrumbs')
        ->assertJsonPath('breadcrumbs.0.url', null)
        ->assertJsonPath('menu.0.children', [])
        ->assertJsonPath('menu.0.active', true);
});

test('unknown associated routes identify the page and destination', function () {
    app(PageRegistry::class)->register(Page::make('broken')->route('missing'));
    $this->withoutExceptionHandling();
    expect(fn () => $this->get('/other'))->toThrow(LogicException::class, "Page 'broken' references unknown route 'missing'");
});

test('route associations do not change route methods paths or middleware', function () {
    Route::getRoutes()->refreshNameLookups();
    $route = Route::getRoutes()->getByName('projects.create');
    $before = [$route->methods(), $route->uri(), $route->gatherMiddleware()];
    $this->getJson('/projects/create')->assertOk();
    expect([$route->methods(), $route->uri(), $route->gatherMiddleware()])->toBe($before);
});
