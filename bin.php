<?php

include_once "vendor/autoload.php";

use Symfony\Component\Console\Application;

$application = new Application();

$application->add(new \Command\WatchProxyCommand());

$application->run();
