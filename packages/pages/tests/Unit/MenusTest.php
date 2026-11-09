<?php

declare(strict_types=1);

use WavyKit\Pages\Menu;
use WavyKit\Pages\Page;
use WavyKit\Pages\PageRegistry;

test('menu registration snapshots nested placements', function () {
    $entry = Menu::page('account')->label('Original');
    $group = Menu::group('group')->children($entry);
    $registry = new PageRegistry;
    $registry->defineMenu('primary', $group);
    $registry->register(Page::make('account'));
    $entry->label('Changed');
    $group->children();
    expect($registry->menuEntries('primary')[0]->children[0]->label)->toBe('Original');
});

test('invalid menu references and duplicate placements fail even in hidden branches', function (array $entries, string $message) {
    $registry = new PageRegistry;
    $registry->register(Page::make('account'));
    $registry->defineMenu('primary', ...$entries);
    expect(fn () => $registry->finalize())->toThrow(LogicException::class, $message);
})->with([
    'missing page' => [[Menu::page('missing')->visibleWhen(fn (): bool => false)], "Menu 'primary' references unknown page 'missing'"],
    'duplicate page' => [[Menu::page('account'), Menu::group('group')->children(Menu::page('account'))], "Menu 'primary' includes page 'account' more than once"],
    'duplicate group' => [[Menu::group('group'), Menu::group('group')], "Menu 'primary' includes group 'group' more than once"],
]);

test('derived and explicit menu names cannot collide in either registration order', function () {
    $registry = new PageRegistry;
    $registry->deriveMenu('primary');
    expect(fn () => $registry->defineMenu('primary'))->toThrow(LogicException::class, 'unique');
    $other = new PageRegistry;
    $other->defineMenu('primary');
    expect(fn () => $other->deriveMenu('primary'))->toThrow(LogicException::class, 'unique');
});

test('cyclic menu builders fail without partially registering the menu', function () {
    $registry = new PageRegistry;
    $group = Menu::group('cycle');
    $group->children($group);
    expect(fn () => $registry->defineMenu('primary', $group))->toThrow(LogicException::class, 'cycle');
    $registry->defineMenu('primary');
    expect($registry->menuEntries('primary'))->toBe([]);
});

test('explicit menus reject changes after finalization and unknown menu names', function () {
    $registry = new PageRegistry;
    $registry->finalize();
    expect(fn () => $registry->defineMenu('late'))->toThrow(LogicException::class, 'before the registry is resolved')
        ->and(fn () => $registry->menuEntries('missing'))->toThrow(LogicException::class, "Unknown menu 'missing'");
});
