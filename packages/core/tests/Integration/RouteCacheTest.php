<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

test('real route cache round trips preserve breadcrumbs, menus, bound labels, and user visibility in fresh processes', function () {
    $base = sys_get_temp_dir().'/wavykit-core-'.bin2hex(random_bytes(8));
    $files = new Filesystem;
    $files->makeDirectory($base.'/bootstrap/cache', 0755, true);
    $files->makeDirectory($base.'/storage/framework/views', 0755, true);
    $files->makeDirectory($base.'/storage/logs', 0755, true);
    $files->makeDirectory($base.'/app', 0755, true);
    $files->copy(__DIR__.'/../Fixtures/CacheApp/app.php', $base.'/bootstrap/app.php');
    $files->put($base.'/bootstrap/providers.php', '<?php return [];');
    $files->put($base.'/composer.json', json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/']]], JSON_THROW_ON_ERROR));
    $run = function (string $mode, array $requests = []) use ($base): array {
        $process = new Process([PHP_BINARY, '-d', 'display_errors=stderr', __DIR__.'/../Fixtures/CacheApp/run.php', $mode, $base, json_encode($requests, JSON_THROW_ON_ERROR)]);
        $process->setTimeout(30);
        $process->mustRun();
        if ($process->getErrorOutput() !== '') {
            fwrite(STDERR, $process->getErrorOutput());
        }

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    };
    $requests = [
        ['uri' => '/projects/42/edit/settings', 'user' => 'alice'],
        ['uri' => '/projects/77/edit', 'user' => ''],
    ];

    try {
        $before = $run('request', $requests);
        expect($before['cached'])->toBeFalse()
            ->and($before['responses'][0]['status'])->toBe(200)
            ->and($before['responses'][0]['data']['breadcrumbs'][1]['label'])->toBe('Apollo')
            ->and($before['responses'][0]['data']['breadcrumbs'][1]['url'])->toBe('http://localhost/projects/42')
            ->and($before['responses'][0]['data']['user'])->toBe('alice')
            ->and($before['responses'][0]['data']['primary'][0]['active'])->toBeTrue()
            ->and($before['responses'][0]['data']['primary'][0]['children'])->toBe([])
            ->and($before['responses'][0]['data']['footer'][0]['children'][0]['label'])->toBe('All projects')
            ->and($before['responses'][0]['data']['footer'][0]['children'][1]['label'])->toBe('Apollo')
            ->and($before['responses'][0]['data']['footer'][0]['children'][1]['url'])->toBe('http://localhost/projects/42')
            ->and($before['responses'][1]['status'])->toBe(200)
            ->and($before['responses'][1]['data']['breadcrumbs'][1]['label'])->toBe('Artemis')
            ->and($before['responses'][1]['data']['user'])->toBeNull()
            ->and($before['responses'][1]['data']['footer'][0]['children'])->toHaveCount(1);

        $files->delete($base.'/label-calls');
        expect($run('cache'))->toBe(['cached' => true])
            ->and($files->exists($base.'/label-calls'))->toBeFalse()
            ->and($files->get($base.'/bootstrap/cache/routes-v7.php'))->not->toContain('label-calls');

        $after = $run('request', $requests);
        expect($after['cached'])->toBeTrue()
            ->and($after['responses'])->toBe($before['responses'])
            ->and($files->exists($base.'/label-calls'))->toBeTrue();
    } finally {
        $files->deleteDirectory($base);
    }
});
