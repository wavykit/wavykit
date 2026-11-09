<?php

declare(strict_types=1);

use WavyKit\Exceptions\InvalidDefinition;
use WavyKit\Pages\Page;
use WavyKit\Pages\PageRegistry;

test('nested and separately contributed pages share one ordered hierarchy', function () {
    $registry = new PageRegistry;
    $registry->register(Page::make('projects')->children(Page::make('later')->order(10)));
    $registry->register(Page::make('first')->parent('projects')->order(-10), Page::make('second')->parent('projects'));

    expect(array_column($registry->children('projects'), 'id'))->toBe(['first', 'second', 'later'])
        ->and(array_column($registry->trail('first'), 'id'))->toBe(['projects', 'first']);
});

test('registration snapshots nested builders', function () {
    $registry = new PageRegistry;
    $child = Page::make('child')->label('Original');
    $parent = Page::make('root')->children($child);
    $registry->register($parent);
    $child->label('Changed')->parent('elsewhere');
    $parent->children(Page::make('new'));

    expect($registry->page('child')->label)->toBe('Original')
        ->and($registry->page('child')->parentId)->toBe('root')
        ->and(count($registry->all()))->toBe(2);
});

test('invalid graphs fail validation', function (array $pages, string $message) {
    $registry = new PageRegistry;
    $registry->register(...$pages);
    expect(fn () => $registry->finalize())->toThrow(InvalidDefinition::class, $message);
})->with([
    'missing parent' => [[Page::make('child')->parent('missing')], "Page 'child' references unknown parent 'missing'"],
    'cycle' => [[Page::make('a')->parent('b'), Page::make('b')->parent('a')], 'cycle'],
    'duplicate route' => [[Page::make('a')->route('shared'), Page::make('b')->route('shared')], "Route 'shared'"],
]);

test('registration errors do not partially register a batch', function () {
    $registry = new PageRegistry;
    $registry->register(Page::make('existing'));
    expect(fn () => $registry->register(Page::make('new'), Page::make('existing')))->toThrow(InvalidDefinition::class)
        ->and(array_column($registry->all(), 'id'))->toBe(['existing']);
});

test('nested and explicit conflicting parents are rejected', function () {
    $registry = new PageRegistry;
    expect(fn () => $registry->register(Page::make('root')->children(Page::make('child')->parent('other'))))
        ->toThrow(InvalidDefinition::class, 'conflicting parents');
});

test('finalization rejects later registration', function () {
    $registry = new PageRegistry;
    $registry->finalize();
    expect(fn () => $registry->register(Page::make('late')))->toThrow(InvalidDefinition::class, 'before the registry is resolved');
});

test('roots and siblings retain registration order on ties', function () {
    $registry = new PageRegistry;
    $registry->register(Page::make('a')->children(Page::make('first'), Page::make('second')), Page::make('b'));
    expect(array_column($registry->children(null), 'id'))->toBe(['a', 'b'])
        ->and(array_column($registry->children('a'), 'id'))->toBe(['first', 'second']);
});
