<?php

declare(strict_types=1);

use WavyKit\Context;
use WavyKit\CoreServiceProvider;
use WavyKit\Current;
use WavyKit\Navigation\Navigation;
use WavyKit\Pages\Pages;

// Laravel at the edges: builders, definitions, registries, and outputs make no
// Laravel calls; readers, the request context, providers, and facades hold that code.
arch('only the edges use Laravel')
    ->expect('WavyKit')
    ->not->toUse('Illuminate')
    ->ignoring([
        Context::class,
        CoreServiceProvider::class,
        Current::class,
        'WavyKit\Facades',
        Pages::class,
        Navigation::class,
        'WavyKit\Tests',
    ]);

arch('source files declare strict types')
    ->expect('WavyKit')
    ->toUseStrictTypes()
    ->ignoring('WavyKit\Tests');
