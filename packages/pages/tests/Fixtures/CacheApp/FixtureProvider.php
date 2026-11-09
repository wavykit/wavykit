<?php

declare(strict_types=1);

namespace WavyKit\Pages\Tests\Fixtures\CacheApp;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use LogicException;
use WavyKit\Pages\Page;
use WavyKit\Pages\PageContext;
use WavyKit\Pages\PageRegistry;
use WavyKit\Pages\Tests\Fixtures\Project;

final class FixtureProvider extends ServiceProvider
{
    public function boot(PageRegistry $pages, Router $router): void
    {
        $router->bind('project', fn (string $id): Project => new Project([
            'id' => $id,
            'name' => $id === '42' ? 'Apollo' : 'Artemis',
        ]));
        $counterPath = $this->app->basePath('label-calls');
        $pages->register(
            Page::make('projects')->route('projects.index')->label('Projects')->children(
                Page::make('projects.show')->route('projects.show')
                    ->label(function (PageContext $context) use ($counterPath): string {
                        $project = $context->parameter('project');
                        if (! $project instanceof Project) {
                            throw new LogicException('Expected bound project.');
                        }
                        file_put_contents($counterPath, "resolved\n", FILE_APPEND);

                        return $project->name;
                    })->children(Page::make('projects.edit')->route('projects.edit')->label('Edit')),
            ),
        );
    }
}
