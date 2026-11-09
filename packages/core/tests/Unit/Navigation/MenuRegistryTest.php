<?php

declare(strict_types=1);

use WavyKit\Navigation\Menu;
use WavyKit\Navigation\MenuRegistry;
use WavyKit\Pages\Page;
use WavyKit\Pages\PageRegistry;

test('menu registration snapshots nested placements', function () {
    $entry = Menu::page('account')->label('Original');
    $group = Menu::group('group')->children($entry);
    $pages = new PageRegistry;
    $menus = new MenuRegistry($pages);
    $menus->define('primary', $group);
    $pages->register(Page::make('account'));
    $entry->label('Changed');
    $group->children();
    expect($menus->entries('primary')[0]->children[0]->label)->toBe('Original');
});

test('invalid menu references and duplicate placements fail even in hidden branches', function (array $entries, string $message) {
    $pages = new PageRegistry;
    $pages->register(Page::make('account'));
    $menus = new MenuRegistry($pages);
    $menus->define('primary', ...$entries);
    expect(fn () => $menus->finalize())->toThrow(LogicException::class, $message);
})->with([
    'missing page' => [[Menu::page('missing')->visibleWhen(fn (): bool => false)], "Menu 'primary' references unknown page 'missing'"],
    'duplicate page' => [[Menu::page('account'), Menu::group('group')->children(Menu::page('account'))], "Menu 'primary' includes page 'account' more than once"],
    'duplicate group' => [[Menu::group('group'), Menu::group('group')], "Menu 'primary' includes group 'group' more than once"],
]);

test('derived and explicit menu names cannot collide in either registration order', function () {
    $menus = new MenuRegistry(new PageRegistry);
    $menus->derive('primary');
    expect(fn () => $menus->define('primary'))->toThrow(LogicException::class, 'unique');
    $other = new MenuRegistry(new PageRegistry);
    $other->define('primary');
    expect(fn () => $other->derive('primary'))->toThrow(LogicException::class, 'unique');
});

test('cyclic menu builders fail without partially registering the menu', function () {
    $menus = new MenuRegistry(new PageRegistry);
    $group = Menu::group('cycle');
    $group->children($group);
    expect(fn () => $menus->define('primary', $group))->toThrow(LogicException::class, 'cycle');
    $menus->define('primary');
    expect($menus->entries('primary'))->toBe([]);
});

test('menus reject changes after finalization and unknown menu names', function () {
    $menus = new MenuRegistry(new PageRegistry);
    $derived = $menus->derive('primary');
    $menus->finalize();
    expect(fn () => $menus->define('late'))->toThrow(LogicException::class, 'before the menu registry is resolved')
        ->and(fn () => $menus->derive('later'))->toThrow(LogicException::class, 'before the menu registry is resolved')
        ->and(fn () => $derived->except('late'))->toThrow(LogicException::class, 'before the menu registry is resolved')
        ->and(fn () => $menus->entries('missing'))->toThrow(LogicException::class, "Unknown menu 'missing'")
        ->and(fn () => $menus->roots('missing'))->toThrow(LogicException::class, "Unknown menu 'missing'")
        ->and(fn () => $menus->exclusions('missing'))->toThrow(LogicException::class, "Unknown menu 'missing'");
});

test('duplicate derived placements and unknown roots or exclusions fail', function () {
    $pages = new PageRegistry;
    $pages->register(Page::make('root')->children(Page::make('child')));
    $menus = new MenuRegistry($pages);
    $menus->derive('primary', 'root', 'child')->except('child');
    expect(fn () => $menus->finalize())->toThrow(LogicException::class, "Menu 'primary' includes page 'child' more than once");

    $other = new MenuRegistry($pages);
    $other->derive('footer', 'missing');
    expect(fn () => $other->finalize())->toThrow(LogicException::class, "Menu 'footer' references unknown page 'missing'");

    $excluded = new MenuRegistry($pages);
    $excluded->derive('primary', 'root')->except('missing');
    expect(fn () => $excluded->finalize())->toThrow(LogicException::class, "Menu 'primary' excludes unknown page 'missing'");
});

test('a failed validation leaves menus open for correction', function () {
    $pages = new PageRegistry;
    $pages->register(Page::make('root'));
    $menus = new MenuRegistry($pages);
    $menus->define('primary', Menu::page('missing'));
    expect(fn () => $menus->finalize())->toThrow(LogicException::class, 'missing');
    $menus->derive('footer', 'root');
    expect(fn () => $menus->finalize())->toThrow(LogicException::class, 'missing');
});

test('derived roots retain authored order and exclusions are read per menu', function () {
    $pages = new PageRegistry;
    $pages->register(Page::make('a')->children(Page::make('first'), Page::make('second')), Page::make('b'));
    $menus = new MenuRegistry($pages);
    $menus->derive('primary', 'b', 'a')->except('first')->except('second');
    $menus->derive('footer', 'a');
    expect(array_column($menus->roots('primary'), 'id'))->toBe(['b', 'a'])
        ->and($menus->exclusions('primary'))->toBe(['first', 'second'])
        ->and($menus->exclusions('footer'))->toBe([])
        ->and($menus->entries('primary'))->toBeNull();
});
