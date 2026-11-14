<?php

declare(strict_types=1);

use Symplify\MonorepoBuilder\Config\MBConfig;
use Symplify\MonorepoBuilder\ValueObject\Option;

// Used for `validate`: packages must require shared dependencies with the same
// constraints. The root installs packages through path repositories, so
// `merge` is not used. The root requires internal packages with an `@dev`
// stability flag that published manifests never use, so they are excluded.
return static function (MBConfig $mbConfig): void {
    $mbConfig->packageDirectories([__DIR__.'/packages']);
    $mbConfig->parameters()->set(Option::EXCLUDE_PACKAGE_VERSION_CONFLICTS, [
        'wavykit/core',
        'wavykit/inertia',
    ]);
};
