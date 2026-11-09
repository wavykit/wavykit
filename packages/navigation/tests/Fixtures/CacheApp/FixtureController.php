<?php

declare(strict_types=1);

namespace WavyKit\Navigation\Tests\Fixtures\CacheApp;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use WavyKit\Navigation\Navigation;

final class FixtureController
{
    public function index(): string
    {
        return 'Projects';
    }

    /** @return array<string, mixed> */
    public function show(Request $request, Navigation $navigation): array
    {
        $request->setUserResolver(fn () => $request->header('X-Test-User') === 'alice' ? new GenericUser(['id' => 'alice']) : null);

        return [
            'primary' => $navigation->menu('primary'),
            'footer' => $navigation->menu('footer'),
        ];
    }
}
