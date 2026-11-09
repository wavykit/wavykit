<?php

declare(strict_types=1);

namespace WavyKit\Tests\Fixtures\CacheApp;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use WavyKit\Navigation\Navigation;
use WavyKit\Pages\Pages;

final class FixtureController
{
    public function index(): string
    {
        return 'Projects';
    }

    /** @return array<string, mixed> */
    public function show(Request $request, Pages $pages, Navigation $navigation): array
    {
        $request->setUserResolver(fn () => $request->header('X-Test-User') === 'alice' ? new GenericUser(['id' => 'alice']) : null);

        return [
            'pageId' => $pages->page()?->id,
            'user' => $pages->context()->user?->getAuthIdentifier(),
            'breadcrumbs' => $navigation->breadcrumbs(),
            'primary' => $navigation->menu('primary'),
            'footer' => $navigation->menu('footer'),
        ];
    }
}
