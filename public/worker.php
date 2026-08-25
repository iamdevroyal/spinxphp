<?php

declare(strict_types=1);

use Spinx\Kernel\Kernel;
use Spinx\Runtime\RoadRunnerAdapter;

require dirname(__DIR__) . '/vendor/autoload.php';

$projectRoot = dirname(__DIR__);
$debug = getenv('SPINX_DEBUG') === '1';

$kernel = new Kernel($projectRoot, $debug);
$adapter = new RoadRunnerAdapter();

$adapter->boot($kernel);
$adapter->serve();
