<?php

declare(strict_types=1);

use WavyKit\Exceptions\InvalidDefinition;
use WavyKit\Exceptions\ResolutionFailed;
use WavyKit\Exceptions\WavyKitException;
use WavyKit\Navigation\Breadcrumb;
use WavyKit\Navigation\DefinedMenu;
use WavyKit\Navigation\MenuEntry;
use WavyKit\Navigation\MenuItem;
use WavyKit\Navigation\MenuItemKind;
use WavyKit\Navigation\MenuRegistry;
use WavyKit\Pages\Page;
use WavyKit\Pages\PageRegistry;

test('both exception types are catchable as WavyKit and logic exceptions', function (string $class) {
    $exception = new $class('failure');

    expect($exception)->toBeInstanceOf(WavyKitException::class)
        ->and($exception)->toBeInstanceOf(LogicException::class);
})->with([InvalidDefinition::class, ResolutionFailed::class]);

test('define returns a builder so menu-level options can be added later', function () {
    $menus = new MenuRegistry(new PageRegistry);

    expect($menus->define('footer'))->toBeInstanceOf(DefinedMenu::class);
});

test('builders expose no mutable public state', function (string $class) {
    $mutable = array_filter(
        (new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC),
        fn (ReflectionProperty $property): bool => ! $property->isReadOnly(),
    );

    expect($mutable)->toBe([]);
})->with([Page::class, MenuEntry::class]);

test('outputs serialize to the documented shapes', function () {
    $crumb = new Breadcrumb('projects', 'Projects', 'https://example.test/projects', true, 'folder');
    $item = new MenuItem(null, 'Settings', null, false, true, [], null, MenuItemKind::Group);

    expect(json_decode(json_encode($crumb, JSON_THROW_ON_ERROR), true))->toBe([
        'pageId' => 'projects',
        'label' => 'Projects',
        'url' => 'https://example.test/projects',
        'current' => true,
        'icon' => 'folder',
    ])->and(json_decode(json_encode($item, JSON_THROW_ON_ERROR), true))->toBe([
        'pageId' => null,
        'label' => 'Settings',
        'url' => null,
        'current' => false,
        'active' => true,
        'children' => [],
        'icon' => null,
        'kind' => 'group',
    ]);
});
