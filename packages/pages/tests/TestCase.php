<?php

declare(strict_types=1);

namespace WavyKit\Pages\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use WavyKit\Pages\PagesServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [PagesServiceProvider::class];
    }
}
