<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WavyKit\Navigation\Navigation;
use WavyKit\Pages\Page;
use WavyKit\Pages\PageRegistry;
use WavyKit\Pages\Pages;

beforeEach(function () {
    Route::get('/dashboard', fn () => 'dashboard')->name('dashboard');
    Route::get('/projects', fn () => 'projects')->name('projects.index');
    Route::get('/projects/create', function (Pages $pages) {
        return ['breadcrumbs' => app(Navigation::class)->breadcrumbs()];
    })->name('projects.create');
    Route::get('/other', function (Pages $pages) {
        return ['page' => $pages->page(), 'breadcrumbs' => app(Navigation::class)->breadcrumbs()];
    })->name('other');

    $registry = app(PageRegistry::class);
    $registry->register(
        Page::make('dashboard')->label('Dashboard')->route('dashboard'),
        Page::make('projects')->label('Projects')->route('projects.index')->children(
            Page::make('projects.create')->label('Create project')->route('projects.create'),
        ),
    );
});

test('existing Laravel routes produce breadcrumbs with a distinct current item', function () {
    $this->getJson('/projects/create')->assertOk()
        ->assertJsonPath('breadcrumbs.0', ['pageId' => 'projects', 'label' => 'Projects', 'url' => 'http://localhost/projects', 'current' => false])
        ->assertJsonPath('breadcrumbs.1', ['pageId' => 'projects.create', 'label' => 'Create project', 'url' => null, 'current' => true]);
});

test('an unregistered route has no page and empty breadcrumbs', function () {
    $this->getJson('/projects/create')->assertOk()->assertJsonCount(2, 'breadcrumbs');
    $this->getJson('/other')->assertOk()->assertJsonPath('page', null)
        ->assertJsonPath('breadcrumbs', []);
});

test('a later unmatched request cannot reuse the previous route page context', function () {
    app(ExceptionHandler::class)->renderable(function (NotFoundHttpException $exception) {
        $pages = app(Pages::class);

        return response()->json([
            'page' => $pages->page(),
            'breadcrumbs' => app(Navigation::class)->breadcrumbs(),
        ], 404);
    });
    $this->getJson('/projects/create')->assertOk();
    $this->getJson('/does-not-exist')->assertNotFound()
        ->assertJsonPath('page', null)
        ->assertJsonPath('breadcrumbs', []);
});

test('route-less ancestors stay in breadcrumbs without links', function () {
    app(PageRegistry::class)->register(Page::make('group')->label('Group')->children(
        Page::make('middle')->route('middle')->children(Page::make('leaf')->route('leaf')),
    ));
    Route::get('/middle', fn () => 'middle')->name('middle');
    Route::get('/leaf', fn (Navigation $navigation) => ['breadcrumbs' => $navigation->breadcrumbs()])->name('leaf');

    $this->getJson('/leaf')->assertOk()->assertJsonCount(3, 'breadcrumbs')
        ->assertJsonPath('breadcrumbs.0.url', null)
        ->assertJsonPath('breadcrumbs.0.label', 'Group')
        ->assertJsonPath('breadcrumbs.1.url', 'http://localhost/middle');
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
