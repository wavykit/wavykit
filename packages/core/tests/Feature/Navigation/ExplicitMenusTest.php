<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use WavyKit\Context;
use WavyKit\Navigation\Menu;
use WavyKit\Navigation\MenuRegistry;
use WavyKit\Navigation\Navigation;
use WavyKit\Pages\Page;
use WavyKit\Pages\PageRegistry;
use WavyKit\Pages\Pages;

beforeEach(function () {
    Route::get('/settings', fn () => 'settings')->name('settings');
    Route::get('/account', fn (Pages $pages, Navigation $navigation) => [
        'breadcrumbs' => app(Navigation::class)->breadcrumbs(),
        'primary' => $navigation->menu('primary'),
        'footer' => $navigation->menu('footer'),
    ])->name('account');
    Route::get('/privacy', fn () => 'privacy')->name('privacy');
    app(PageRegistry::class)->register(
        Page::make('settings')->label('Settings')->route('settings')->children(
            Page::make('account')->label('Account')->route('account')->icon('user'),
        ),
        Page::make('privacy')->label('Privacy')->route('privacy'),
    );
});

test('named menus place the same page differently without changing breadcrumbs', function () {
    app(MenuRegistry::class)->define('primary',
        Menu::group('settings')->label('Settings')->children(Menu::page('account')),
    );
    app(MenuRegistry::class)->define('footer',
        Menu::page('privacy'),
        Menu::group('your-account')->label('Your account')->icon('footer')->children(
            Menu::page('account')->label('Profile')->icon('profile'),
        ),
    );

    $this->getJson('/account')->assertOk()
        ->assertJsonPath('breadcrumbs.0.label', 'Settings')
        ->assertJsonPath('breadcrumbs.1.label', 'Account')
        ->assertJsonPath('primary.0.kind', 'group')
        ->assertJsonPath('primary.0.pageId', null)
        ->assertJsonPath('primary.0.url', null)
        ->assertJsonPath('primary.0.current', false)
        ->assertJsonPath('primary.0.active', true)
        ->assertJsonPath('primary.0.children.0.label', 'Account')
        ->assertJsonPath('primary.0.children.0.icon', 'user')
        ->assertJsonPath('primary.0.children.0.current', true)
        ->assertJsonPath('footer.0.pageId', 'privacy')
        ->assertJsonPath('footer.0.active', false)
        ->assertJsonPath('footer.1.children.0.label', 'Profile')
        ->assertJsonPath('footer.1.children.0.icon', 'profile')
        ->assertJsonPath('footer.1.active', true);
});

test('linked menu parents inherit activity through explicitly placed children', function () {
    app(MenuRegistry::class)->define('primary', Menu::page('privacy')->children(Menu::page('account')));
    app(MenuRegistry::class)->define('footer');

    $this->getJson('/account')->assertOk()
        ->assertJsonPath('primary.0.url', 'http://localhost/privacy')
        ->assertJsonPath('primary.0.active', true)
        ->assertJsonPath('primary.0.current', false);
});

test('explicit entries never implicitly import page descendants', function () {
    app(MenuRegistry::class)->define('primary', Menu::page('settings'));
    app(MenuRegistry::class)->define('footer');
    $this->getJson('/account')->assertOk()
        ->assertJsonPath('primary.0.active', true)
        ->assertJsonPath('primary.0.children', []);
});

test('derived and explicit menus coexist and preserve activity for absent descendants', function () {
    app(MenuRegistry::class)->derive('primary', 'settings')->except('account');
    app(MenuRegistry::class)->define('footer', Menu::page('account'));
    app(PageRegistry::class)->register(Page::make('account.edit')->parent('account')->route('account.edit'));
    Route::get('/account/edit', fn (Navigation $navigation) => [
        'primary' => $navigation->menu('primary'),
        'footer' => $navigation->menu('footer'),
    ])->name('account.edit');

    $this->getJson('/account/edit')->assertOk()
        ->assertJsonPath('primary.0.active', true)
        ->assertJsonPath('primary.0.children', [])
        ->assertJsonPath('footer.0.pageId', 'account')
        ->assertJsonPath('footer.0.active', true)
        ->assertJsonPath('footer.0.current', false)
        ->assertJsonPath('footer.0.icon', 'user');
});

test('hidden resource placements skip parameter mapping as well as labels', function () {
    app(PageRegistry::class)->register(Page::make('resource')->route('resource')
        ->label(fn () => throw new LogicException('Must not resolve resource label'))
        ->parameters(fn () => throw new LogicException('Must not resolve resource parameters')));
    Route::get('/resource/{resource}', fn () => 'resource')->name('resource');
    app(MenuRegistry::class)->define('primary', Menu::page('resource')->visibleWhen(fn (): bool => false));
    app(MenuRegistry::class)->define('footer');

    $this->getJson('/account')->assertOk()->assertJsonPath('primary', []);
});

test('request visibility is isolated across guest allowed denied and guest requests', function () {
    $calls = 0;
    app(MenuRegistry::class)->define('primary',
        Menu::page('privacy'),
        Menu::group('restricted')->label('Restricted')->visibleWhen(function (Context $context) use (&$calls): bool {
            $calls++;

            return $context->user instanceof GenericUser && $context->user->allowed === true;
        })->children(Menu::page('account')->label(function (Context $context): string {
            return 'Account for '.$context->user->getAuthIdentifier();
        })),
    );
    Route::get('/visible/{identity}', function (Request $request, string $identity, Navigation $navigation) {
        $request->setUserResolver(fn () => match ($identity) {
            'allowed' => new GenericUser(['id' => 'alice', 'allowed' => true]),
            'denied' => new GenericUser(['id' => 'bob', 'allowed' => false]),
            default => null,
        });

        return $navigation->menu('primary');
    });

    $registry = app(MenuRegistry::class);
    expect($calls)->toBe(0);
    $this->getJson('/visible/guest')->assertOk()->assertJsonCount(1);
    $this->getJson('/visible/allowed')->assertOk()->assertJsonCount(2)->assertJsonPath('1.children.0.label', 'Account for alice');
    $this->getJson('/visible/denied')->assertOk()->assertJsonCount(1);
    $this->getJson('/visible/guest')->assertOk()->assertJsonCount(1);
    expect($calls)->toBe(4)->and(app(MenuRegistry::class))->toBe($registry);
});

test('hidden entries skip callbacks and empty groups disappear while linked parents stay', function () {
    app(MenuRegistry::class)->define('primary',
        Menu::group('empty')->label(fn () => throw new LogicException('Empty group label must not run'))->children(
            Menu::page('account')->visibleWhen(fn (): bool => false)->label(fn () => throw new LogicException('Hidden label must not run')),
        ),
        Menu::page('privacy')->children(
            Menu::group('hidden')->visibleWhen(fn (): bool => false)->children(Menu::page('settings')->label(fn () => throw new LogicException('Hidden subtree must not run'))),
        ),
    );
    app(MenuRegistry::class)->define('footer');

    $this->getJson('/account')->assertOk()->assertJsonCount(1, 'primary')
        ->assertJsonPath('primary.0.pageId', 'privacy')
        ->assertJsonPath('primary.0.children', []);
});

test('invalid visibility values and exceptions identify the menu and entry', function (Closure $predicate, string $message) {
    app(MenuRegistry::class)->define('primary', Menu::page('account')->visibleWhen($predicate));
    app(MenuRegistry::class)->define('footer');
    $this->withoutExceptionHandling();
    expect(fn () => $this->get('/account'))->toThrow(LogicException::class, $message);
})->with([
    'non boolean' => [fn (): int => 1, "Visibility callback for menu 'primary' entry 'account' must return a bool"],
    'exception' => [fn () => throw new LogicException('predicate failed'), "Could not resolve visibility for menu 'primary' entry 'account': predicate failed"],
]);

test('placement labels are resolved with request context and checked', function () {
    app(MenuRegistry::class)->define('primary', Menu::page('account')->label(fn (): int => 123));
    app(MenuRegistry::class)->define('footer');
    $this->withoutExceptionHandling();
    expect(fn () => $this->get('/account'))->toThrow(LogicException::class, "Label callback for menu 'primary' entry 'account' must return a string");
});
