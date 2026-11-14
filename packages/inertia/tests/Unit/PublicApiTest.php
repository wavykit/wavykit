<?php

declare(strict_types=1);

use WavyKit\Inertia\WavyKitProps;

test('the supported API changes only deliberately', function () {
    $reflection = new ReflectionClass(WavyKitProps::class);
    $methods = array_values(array_map(
        fn (ReflectionMethod $method): string => $method->getName(),
        array_filter(
            $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
            fn (ReflectionMethod $method): bool => ! str_contains((string) $method->getDocComment(), '@internal'),
        ),
    ));
    sort($methods);

    expect($methods)->toBe(['make', 'menus']);
});
