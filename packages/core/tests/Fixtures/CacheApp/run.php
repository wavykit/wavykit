<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;

require dirname(__DIR__, 5).'/vendor/autoload.php';

$app = require $argv[2].'/bootstrap/app.php';
if ($argv[1] === 'cache') {
    $kernel = $app->make(ConsoleKernel::class);
    $exit = $kernel->call('route:cache');
    if ($exit !== 0) {
        fwrite(STDERR, $kernel->output());
        exit($exit);
    }
    echo json_encode(['cached' => is_file($app->getCachedRoutesPath())], JSON_THROW_ON_ERROR);
    exit;
}

$kernel = $app->make(HttpKernel::class);
$responses = [];
foreach (json_decode($argv[3], true, flags: JSON_THROW_ON_ERROR) as $spec) {
    $request = Request::create('http://localhost'.$spec['uri'], server: [
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_TEST_USER' => $spec['user'],
    ]);
    $response = $kernel->handle($request);
    $responses[] = [
        'status' => $response->getStatusCode(),
        'data' => json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR),
    ];
    $kernel->terminate($request, $response);
}
echo json_encode(['cached' => $app->routesAreCached(), 'responses' => $responses], JSON_THROW_ON_ERROR);
