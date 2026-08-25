<?php

declare(strict_types=1);

use Spinx\Kernel\Kernel;
use Spinx\Runtime\SwooleAdapter;

require dirname(__DIR__) . '/vendor/autoload.php';

$projectRoot = dirname(__DIR__);
$debug = getenv('SPINX_DEBUG') === '1';

$config = json_decode((string) file_get_contents($projectRoot . '/spinx.json'), true) ?? [];
$driver = $config['driver'] ?? 'roadrunner';

if ($driver !== 'swoole') {
    // Not fatal — someone may be intentionally running this script
    // directly to test Swoole without switching the project's default —
    // but this exact mismatch is the one documented in SwooleAdapter's
    // class docblock: ConnectionManagerFactory reads spinx.json's
    // "driver" to decide RoadRunner-style vs Swoole-coroutine-pool
    // connection handling, so running the Swoole HTTP server while that
    // config still says "roadrunner" gives you the wrong pooling
    // strategy for the server actually running.
    fwrite(STDERR, "[Spinx] WARNING: spinx.json's \"driver\" is \"{$driver}\", but this process is running the Swoole HTTP server directly.\n");
    fwrite(STDERR, "                 Database connection pooling will use the wrong strategy. Run `spinx driver:swap swoole` to fix this.\n");
}

$swooleConfig = $config['swoole'] ?? [];

$kernel = new Kernel($projectRoot, $debug);
$adapter = new SwooleAdapter(
    host: $swooleConfig['host'] ?? '0.0.0.0',
    port: (int) ($swooleConfig['port'] ?? 9501),
    workerCount: (int) ($swooleConfig['workers'] ?? 4),
);

$adapter->boot($kernel);
$adapter->serve();
