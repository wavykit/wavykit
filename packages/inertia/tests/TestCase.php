<?php

declare(strict_types=1);

namespace WavyKit\Inertia\Tests;

use Inertia\ServiceProvider as InertiaServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use WavyKit\CoreServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, InertiaServiceProvider::class];
    }
}
