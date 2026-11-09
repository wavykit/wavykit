<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use WavyKit\Context;
use WavyKit\Navigation\Navigation;
use WavyKit\Pages\Page;
use WavyKit\Pages\PageRegistry;
use WavyKit\Pages\Pages;
use WavyKit\Tests\Fixtures\Project;

beforeEach(function () {
    Route::bind('project', fn (string $id): Project => new Project([
        'id' => $id,
        'name' => $id === '42' ? 'Apollo' : 'Artemis',
    ]));
    Route::get('/projects', fn () => 'projects')->name('projects.index');
    Route::get('/projects/{project}', fn () => 'details')->name('projects.show');
    Route::get('/projects/{project}/edit/{tab?}', function (Pages $pages) {
        return ['breadcrumbs' => app(Navigation::class)->breadcrumbs()];
    })->middleware(SubstituteBindings::class)->name('projects.edit');

    app(PageRegistry::class)->register(
        Page::make('projects')->route('projects.index')->label('Projects')->children(
            Page::make('projects.show')->route('projects.show')
                ->label(function (Context $context): string {
                    $project = $context->parameter('project');
                    if (! $project instanceof Project) {
                        throw new LogicException('Expected the Laravel-bound project.');
                    }

                    return $project->name;
                })
                ->children(
                    Page::make('projects.edit')->route('projects.edit')->label('Edit'),
                ),
        ),
    );
});

test('bound resource labels and ancestor URLs remain isolated across requests', function () {
    $registry = app(PageRegistry::class);
    $this->getJson('/projects/42/edit/settings')->assertOk()
        ->assertJsonPath('breadcrumbs.1.label', 'Apollo')
        ->assertJsonPath('breadcrumbs.1.url', 'http://localhost/projects/42')
        ->assertJsonPath('breadcrumbs.2.label', 'Edit')
        ->assertJsonPath('breadcrumbs.2.url', null);

    $this->getJson('/projects/77/edit')->assertOk()
        ->assertJsonPath('breadcrumbs.1.label', 'Artemis')
        ->assertJsonPath('breadcrumbs.1.url', 'http://localhost/projects/77');

    expect(app(PageRegistry::class))->toBe($registry)
        ->and($registry->page('projects.show')->label)->toBeInstanceOf(Closure::class);
});

test('ancestor callbacks see the active page rather than the ancestor being rendered', function () {
    $seen = [];
    app(PageRegistry::class)->register(
        Page::make('context.root')->label(function (Context $context) use (&$seen): string {
            $seen[] = $context->page?->id;

            return 'Context root';
        })->children(Page::make('context.child')->route('context.child')),
    );
    Route::get('/context', fn (Navigation $navigation) => $navigation->breadcrumbs())->name('context.child');
    expect($seen)->toBe([]);
    $this->getJson('/context')->assertOk();
    expect($seen)->toBe(['context.child']);
});

test('explicit parameter mapping supports differently named child parameters', function () {
    Route::bind('item', fn (string $id): Project => new Project(['id' => $id, 'name' => 'Mapped project']));
    Route::get('/mapped/projects/{project}', fn () => 'show')->name('mapped.show');
    Route::get('/mapped/items/{item}/edit', fn (Navigation $navigation) => $navigation->breadcrumbs())
        ->middleware(SubstituteBindings::class)->name('mapped.edit');
    app(PageRegistry::class)->register(
        Page::make('mapped.show')->route('mapped.show')->label('Mapped project')
            ->parameters(fn (Context $context): array => [
                'project' => $context->parameter('item'),
                'extra' => 'must not become a query string',
            ])->children(Page::make('mapped.edit')->route('mapped.edit')->label('Edit')),
    );

    $this->getJson('/mapped/items/42/edit')->assertOk()
        ->assertJsonPath('0.url', 'http://localhost/mapped/projects/42');
});

test('missing destination parameters identify the page and route', function () {
    app(PageRegistry::class)->register(
        Page::make('needs.resource')->route('needs.resource')->children(Page::make('no.resource')->route('no.resource')),
    );
    Route::get('/needs/{resource}', fn () => 'resource')->name('needs.resource');
    Route::get('/no-resource', fn (Navigation $navigation) => $navigation->breadcrumbs())->name('no.resource');
    $this->withoutExceptionHandling();

    expect(fn () => $this->get('/no-resource'))->toThrow(LogicException::class, "page 'needs.resource' and route 'needs.resource'");
});

test('missing callback parameters retain the active and resolved page identifiers', function () {
    app(PageRegistry::class)->register(Page::make('missing')->route('missing')
        ->label(fn (Context $context): mixed => $context->parameter('absent')));
    Route::get('/missing', fn (Navigation $navigation) => $navigation->breadcrumbs())->name('missing');
    $this->withoutExceptionHandling();

    expect(fn () => $this->get('/missing'))->toThrow(LogicException::class, "Could not resolve label for page 'missing': Active page 'missing' is missing route parameter 'absent'");
});

test('invalid label results identify the page', function () {
    app(PageRegistry::class)->register(Page::make('invalid.label')->route('invalid.label')->label(fn (): int => 123));
    Route::get('/invalid-label', fn (Navigation $navigation) => $navigation->breadcrumbs())->name('invalid.label');
    $this->withoutExceptionHandling();
    expect(fn () => $this->get('/invalid-label'))->toThrow(LogicException::class, "Label callback for page 'invalid.label' must return a string");
});

test('invalid parameter mapping results identify the page', function (mixed $result) {
    app(PageRegistry::class)->register(Page::make('invalid.mapping')->route('invalid.mapping')->parameters(fn (): mixed => $result)
        ->children(Page::make('mapping.child')->route('mapping.child')));
    Route::get('/invalid-mapping/{project}', fn () => 'show')->name('invalid.mapping');
    Route::get('/mapping-child', fn (Navigation $navigation) => $navigation->breadcrumbs())->name('mapping.child');
    $this->withoutExceptionHandling();
    expect(fn () => $this->get('/mapping-child'))->toThrow(LogicException::class, "Parameter callback for page 'invalid.mapping' must return an array keyed by route parameter name");
})->with(['non-array' => 'invalid', 'numeric keys' => [[42]]]);
