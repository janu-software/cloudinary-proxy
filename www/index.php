<?php

declare(strict_types=1);

use JanuSoftware\MediaServe\App;
use function Safe\realpath;


require __DIR__ . '/../vendor/autoload.php';

$application = new App(realpath(__DIR__ . '/../'));
$application->runCloudinaryCache();
