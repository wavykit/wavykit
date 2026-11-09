<?php

declare(strict_types=1);

namespace WavyKit\Pages\Tests\Fixtures\CacheApp;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use WavyKit\Pages\Pages;

final class FixtureController
{
    public function index(): string
    {
        return 'Projects';
    }

    /** @return array<string, mixed> */
    public function show(Request $request, Pages $pages): array
    {
        $request->setUserResolver(fn () => $request->header('X-Test-User') === 'alice' ? new GenericUser(['id' => 'alice']) : null);

        return [
            'pageId' => $pages->page()?->id,
            'breadcrumbs' => $pages->breadcrumbs(),
            'primary' => $pages->navigation('primary'),
            'footer' => $pages->navigation('footer'),
        ];
    }
}
