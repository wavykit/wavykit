<?php

declare(strict_types=1);

use Symplify\MonorepoBuilder\Config\MBConfig;

// Used for `validate`: packages must require shared dependencies with the same
// constraints. The root installs packages through path repositories, so
// `merge` is not used.
return static function (MBConfig $mbConfig): void {
    $mbConfig->packageDirectories([__DIR__.'/packages']);
};
