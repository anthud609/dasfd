<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use BIMS\Core\Bootstrap;
use Psr\Log\LoggerInterface;

// Instantiate all your wiring
$bootstrap = new Bootstrap(dirname(__DIR__));

// Grab services from the container
$container = $bootstrap->getContainer();
/** @var LoggerInterface $logger */
$logger = $bootstrap->getLogger();

// …here you hand off to your router/framework…
$logger->info('Now dispatching the HTTP request');

// e.g., (Slim example)
// $app = $container->get(\Slim\App::class);
// $app->run();
tetser();