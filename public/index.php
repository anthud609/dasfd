<?php
declare(strict_types=1);

use BIMS\Core\Bootstrap;
use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response;

// ❶ Load Composer’s PSR-4 autoloader
require __DIR__ . '/../vendor/autoload.php';
// 1) Your existing bootstrap & container
$bootstrap  = new Bootstrap(dirname(__DIR__));
$container  = $bootstrap->getContainer();
\Sentry\init([
  'dsn' => 'https://2978578c4a02ab8f5221003d3a4eabc9@o4509296255959040.ingest.us.sentry.io/4509296257269761',
]);
// 2) Tell Slim to use your container
AppFactory::setContainer($container);

// 3) Create the App
$app = AppFactory::create();

// 4) (Optional) Add routing & body-parsing middleware
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// 2) Then your Correlation → Logging
$app->add(\BIMS\Core\Middleware\CorrelationMiddleware::class);
$app->add(\BIMS\Core\Middleware\HttpLoggingMiddleware::class);

// 3) Finally error handling
$app->addErrorMiddleware(
    (bool) ($_ENV['APP_DEBUG'] ?? false),
    true,
    true
);

// 6) Define a demo route
$app->get('/', function ($request, $response) {
    $response->getBody()->write('Hello, Slim is installed!');
    return $response;
});

// 7) Error middleware (for detailed errors in debug)
$app->addErrorMiddleware(
    (bool) ($_ENV['APP_DEBUG'] ?? false),
    true,
    true
);

// 8) Run the app
$app->run();

try {
  $this->functionFailsForSure();
} catch (\Throwable $exception) {
  \Sentry\captureException($exception);
}