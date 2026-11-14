<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use WavyKit\Context;
use WavyKit\Exceptions\InvalidDefinition;
use WavyKit\Exceptions\ResolutionFailed;
use WavyKit\Inertia\Tests\Fixtures\Project;
use WavyKit\Inertia\WavyKitProps;
use WavyKit\Navigation\MenuEntry;
use WavyKit\Navigation\MenuRegistry;
use WavyKit\Pages\Page;
use WavyKit\Pages\PageRegistry;

const INERTIA = ['X-Inertia' => 'true'];

beforeEach(function () {
    $this->labels = 0;
    Route::bind('project', fn (string $id): Project => new Project($id, $id === '42' ? 'Apollo' : 'Artemis'));
    Route::get('/projects', fn () => Inertia::render('Projects'))->name('projects.index');
    Route::get('/projects/{project}', fn () => Inertia::render('Project', ['other' => 'value']))
        ->middleware(SubstituteBindings::class)->name('projects.show');
    Route::get('/unregistered', fn () => Inertia::render('Unregistered'))->name('unregistered');

    app(PageRegistry::class)->register(
        Page::make('projects')->route('projects.index')->label('Projects')->children(
            Page::make('projects.show')->route('projects.show')->label(function (Context $context): string {
                $this->labels++;
                $project = $context->parameter('project');

                return $project instanceof Project ? $project->name : 'Unknown';
            }),
        ),
    );
    // Global menus leave out resource pages, which need the resource's route parameter.
    app(MenuRegistry::class)->derive('primary', 'projects')->except('projects.show');
    app(MenuRegistry::class)->define('footer', MenuEntry::page('projects')->label('All projects'));
});

test('it shares the page ID, breadcrumbs, and selected menus after route-model binding', function () {
    Inertia::share('wavykit', WavyKitProps::make()->menus('footer', 'primary'));

    $response = $this->getJson('/projects/42', INERTIA)->assertOk()
        ->assertJsonPath('props.wavykit.pageId', 'projects.show')
        ->assertJsonPath('props.wavykit.breadcrumbs.0.label', 'Projects')
        ->assertJsonPath('props.wavykit.breadcrumbs.1', [
            'pageId' => 'projects.show',
            'label' => 'Apollo',
            'url' => 'http://localhost/projects/42',
            'current' => true,
            'icon' => null,
        ])
        ->assertJsonPath('props.wavykit.menus.primary.0.active', true)
        ->assertJsonPath('props.wavykit.menus.primary.0.current', false)
        ->assertJsonPath('props.wavykit.menus.footer.0.label', 'All projects');

    expect(array_keys($response->json('props.wavykit.menus')))->toBe(['footer', 'primary']);
});

test('the application chooses the prop key', function () {
    Inertia::share('experience', WavyKitProps::make());

    $this->getJson('/projects', INERTIA)->assertOk()
        ->assertJsonPath('props.experience.pageId', 'projects')
        ->assertJsonMissingPath('props.wavykit');
});

test('no menus serializes as an empty object', function () {
    Inertia::share('wavykit', WavyKitProps::make());

    $content = $this->getJson('/projects', INERTIA)->assertOk()->getContent();

    expect($content)->toContain('"menus":{}');
});

test('unregistered routes share no page and inactive menus', function () {
    Inertia::share('wavykit', WavyKitProps::make()->menus('primary'));

    $this->getJson('/unregistered', INERTIA)->assertOk()
        ->assertJsonPath('props.wavykit.pageId', null)
        ->assertJsonPath('props.wavykit.breadcrumbs', [])
        ->assertJsonPath('props.wavykit.menus.primary.0.active', false);
});

test('a partial reload that excludes the prop does not resolve it', function () {
    Inertia::share('wavykit', WavyKitProps::make()->menus('primary'));

    $this->getJson('/projects/42', [
        ...INERTIA,
        'X-Inertia-Partial-Component' => 'Project',
        'X-Inertia-Partial-Data' => 'other',
    ])->assertOk()
        ->assertJsonPath('props.other', 'value')
        ->assertJsonMissingPath('props.wavykit');

    expect($this->labels)->toBe(0);
});

test('it can be wrapped in Inertia prop types such as deferred props', function () {
    Inertia::share('wavykit', Inertia::defer(fn () => WavyKitProps::make()->menus('primary')));

    $this->getJson('/projects/42', INERTIA)->assertOk()
        ->assertJsonMissingPath('props.wavykit')
        ->assertJsonPath('deferredProps.default', ['wavykit']);
    expect($this->labels)->toBe(0);

    $this->getJson('/projects/42', [
        ...INERTIA,
        'X-Inertia-Partial-Component' => 'Project',
        'X-Inertia-Partial-Data' => 'wavykit',
    ])->assertOk()
        ->assertJsonPath('props.wavykit.breadcrumbs.1.label', 'Apollo')
        ->assertJsonPath('props.wavykit.menus.primary.0.pageId', 'projects');
});

test('menu names are checked when declared and when resolved', function () {
    expect(fn () => WavyKitProps::make()->menus('primary', 'primary'))
        ->toThrow(InvalidDefinition::class, "Menu 'primary' must have a non-empty name and be shared once.")
        ->and(fn () => WavyKitProps::make()->menus(' '))
        ->toThrow(InvalidDefinition::class);

    Inertia::share('wavykit', WavyKitProps::make()->menus('missing'));
    $this->withoutExceptionHandling();

    expect(fn () => $this->getJson('/projects', INERTIA))
        ->toThrow(ResolutionFailed::class, "Unknown menu 'missing'.");
});
