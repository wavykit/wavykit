<?php

declare(strict_types=1);

namespace WavyKit\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use WavyKit\CoreServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class];
    }
}
