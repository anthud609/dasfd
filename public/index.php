<?php
declare(strict_types=1);

use BIMS\Core\Bootstrap;
use Slim\Factory\AppFactory;
use BIMS\Core\Error\AdvancedErrorHandler;

// 1) Autoload
require __DIR__ . '/../vendor/autoload.php';

// 2) Bootstrap + Container
$bootstrap = new Bootstrap(dirname(__DIR__));
$container = $bootstrap->getContainer();

// 3) Initialize Sentry
\Sentry\init([
    'dsn' => 'https://<key>@o4509296255959040.ingest.us.sentry.io/<project>',
    // plus any options you want
]);

// 4) Register global error handlers
$container->get(AdvancedErrorHandler::class)->registerHandlers();

// 5) Tell Slim to use your container
AppFactory::setContainer($container);
$app = AppFactory::create();

// 6) Middlewares, routes, etc.
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->add(\BIMS\Core\Middleware\CorrelationMiddleware::class);
$app->add(\BIMS\Core\Middleware\HttpLoggingMiddleware::class);

// 7) Slim's error middleware (for HTTP error responses)
$app->addErrorMiddleware(
    (bool) ($_ENV['APP_DEBUG'] ?? false),
    true,
    true
);

// 8) Route
$app->get('/', function ($request, $response) {
    $response->getBody()->write('Hello, Slim is installed!');
    return $response;
});

// 9) Run
$app->run();
