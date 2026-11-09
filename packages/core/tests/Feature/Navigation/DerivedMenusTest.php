<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WavyKit\Context;
use WavyKit\Exceptions\ResolutionFailed;
use WavyKit\Navigation\MenuRegistry;
use WavyKit\Navigation\Navigation;
use WavyKit\Pages\Page;
use WavyKit\Pages\PageRegistry;
use WavyKit\Tests\Fixtures\Project;

beforeEach(function () {
    Route::get('/dashboard', fn () => 'dashboard')->name('dashboard');
    Route::get('/projects', fn () => 'projects')->name('projects.index');
    Route::get('/projects/create', fn (Navigation $navigation) => $navigation->menu('primary'))->name('projects.create');
    Route::get('/other', fn (Navigation $navigation) => $navigation->menu('primary'))->name('other');

    app(PageRegistry::class)->register(
        Page::make('dashboard')->label('Dashboard')->route('dashboard'),
        Page::make('projects')->label('Projects')->route('projects.index')->children(
            Page::make('projects.create')->label('Create project')->route('projects.create'),
        ),
    );
});

test('derived menus nest pages and separate current from active', function () {
    app(MenuRegistry::class)->derive('primary', 'dashboard', 'projects');

    $this->getJson('/projects/create')->assertOk()
        ->assertJsonPath('0.active', false)
        ->assertJsonPath('1.current', false)
        ->assertJsonPath('1.active', true)
        ->assertJsonPath('1.kind', 'page')
        ->assertJsonPath('1.url', 'http://localhost/projects')
        ->assertJsonPath('1.children.0.current', true);
});

test('an unregistered route clears active state without removing global navigation', function () {
    app(MenuRegistry::class)->derive('primary', 'dashboard', 'projects');

    $this->getJson('/projects/create')->assertOk()->assertJsonPath('1.active', true);
    $this->getJson('/other')->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('1.active', false)
        ->assertJsonPath('1.children.0.current', false);
});

test('a later unmatched request cannot reuse the previous route menu state', function () {
    app(MenuRegistry::class)->derive('primary', 'dashboard', 'projects');
    app(ExceptionHandler::class)->renderable(fn (NotFoundHttpException $exception) => response()->json(app(Navigation::class)->menu('primary'), 404));

    $this->getJson('/projects/create')->assertOk();
    $this->getJson('/does-not-exist')->assertNotFound()->assertJsonPath('1.active', false);
});

test('excluded subtrees are omitted while route-less ancestors stay active', function () {
    app(PageRegistry::class)->register(Page::make('group')->label('Group')->children(
        Page::make('hidden')->route('hidden')->children(Page::make('leaf')->route('leaf')),
    ));
    app(MenuRegistry::class)->derive('grouped', 'group')->except('hidden');
    Route::get('/hidden', fn () => 'hidden')->name('hidden');
    Route::get('/leaf', fn (Navigation $navigation) => $navigation->menu('grouped'))->name('leaf');

    $this->getJson('/leaf')->assertOk()
        ->assertJsonPath('0.url', null)
        ->assertJsonPath('0.children', [])
        ->assertJsonPath('0.active', true);
});

test('exclusions apply only to the menu that declares them', function () {
    app(MenuRegistry::class)->derive('primary', 'projects')->except('projects.create');
    app(MenuRegistry::class)->derive('sitemap', 'projects');
    Route::get('/menus', fn (Navigation $navigation) => [
        'primary' => $navigation->menu('primary'),
        'sitemap' => $navigation->menu('sitemap'),
    ])->name('menus');

    $this->getJson('/menus')->assertOk()
        ->assertJsonPath('primary.0.children', [])
        ->assertJsonPath('sitemap.0.children.0.pageId', 'projects.create');
});

test('excluded resource pages keep their ancestors active without resolving their callbacks', function () {
    Route::bind('project', fn (string $id): Project => new Project(['id' => $id, 'name' => 'Apollo']));
    Route::get('/projects/{project}', fn () => 'details')->name('projects.show');
    Route::get('/projects/{project}/edit', fn (Navigation $navigation) => $navigation->menu('resources'))
        ->middleware(SubstituteBindings::class)->name('projects.edit');
    app(PageRegistry::class)->register(
        Page::make('projects.show')->parent('projects')->route('projects.show')
            ->label(fn (Context $context): string => throw new LogicException('Excluded label must not run'))
            ->routeParameters(fn (Context $context): array => throw new LogicException('Excluded parameters must not run'))
            ->children(Page::make('projects.edit')->route('projects.edit')->label('Edit')),
    );
    app(MenuRegistry::class)->derive('resources', 'projects')->except('projects.create', 'projects.show');

    $this->getJson('/projects/42/edit')->assertOk()
        ->assertJsonPath('0.label', 'Projects')
        ->assertJsonPath('0.active', true)
        ->assertJsonPath('0.children', []);
});

test('unknown menus fail with the menu name', function () {
    app(MenuRegistry::class)->derive('primary', 'dashboard');
    $this->withoutExceptionHandling();
    Route::get('/unknown-menu', fn (Navigation $navigation) => $navigation->menu('missing'))->name('unknown.menu');

    expect(fn () => $this->get('/unknown-menu'))->toThrow(ResolutionFailed::class, "Unknown menu 'missing'");
});
