<?php

declare(strict_types=1);

namespace WavyKit\Tests\Fixtures\CacheApp;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use LogicException;
use WavyKit\Context;
use WavyKit\Navigation\MenuEntry;
use WavyKit\Navigation\MenuRegistry;
use WavyKit\Pages\Page;
use WavyKit\Pages\PageRegistry;
use WavyKit\Tests\Fixtures\Project;

final class FixtureProvider extends ServiceProvider
{
    public function boot(PageRegistry $pages, MenuRegistry $menus, Router $router): void
    {
        $router->bind('project', fn (string $id): Project => new Project([
            'id' => $id,
            'name' => $id === '42' ? 'Apollo' : 'Artemis',
        ]));
        $counterPath = $this->app->basePath('label-calls');
        $pages->register(
            Page::make('projects')->route('projects.index')->label('Projects')->children(
                Page::make('projects.show')->route('projects.show')
                    ->label(function (Context $context) use ($counterPath): string {
                        $project = $context->parameter('project');
                        if (! $project instanceof Project) {
                            throw new LogicException('Expected bound project.');
                        }
                        file_put_contents($counterPath, "resolved\n", FILE_APPEND);

                        return $project->name;
                    })->children(Page::make('projects.edit')->route('projects.edit')->label('Edit')),
            ),
        );
        $menus->derive('primary', 'projects')->except('projects.show');
        $menus->define('footer', MenuEntry::group('tools')->label('Tools')->children(
            MenuEntry::page('projects')->label('All projects'),
            MenuEntry::page('projects.show')->visibleWhen(fn (Context $context): bool => $context->user !== null),
        ));
    }
}
