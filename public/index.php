<?php
declare(strict_types=1);

use BIMS\Core\Bootstrap;
use Slim\Factory\AppFactory;
use BIMS\Core\Error\AdvancedErrorHandler;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

// 1) Autoload
require __DIR__ . '/../vendor/autoload.php';

// 2) Bootstrap + Container
$bootstrap = new Bootstrap(dirname(__DIR__));
$container = $bootstrap->getContainer();

// 3) Initialize Sentry
\Sentry\init([
    'dsn'           => 'https://YOUR_KEY@o4509296255959040.ingest.us.sentry.io/YOUR_PROJECT',
    'environment'   => $_ENV['APP_ENV'] ?? 'production',
    'error_types'   => E_ALL,
]);

// 4) Register global error handlers for PHP errors & shutdowns
$container->get(AdvancedErrorHandler::class)->registerHandlers();

// 5) Create the Slim app
AppFactory::setContainer($container);
$app = AppFactory::create();

// 6) Attach your HTTP middlewares
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->add(\BIMS\Core\Middleware\CorrelationMiddleware::class);
$app->add(\BIMS\Core\Middleware\HttpLoggingMiddleware::class);

// 7) Add Slim’s ErrorMiddleware—and wrap its default handler to send to Sentry
$errorMiddleware = $app->addErrorMiddleware(
    (bool) ($_ENV['APP_DEBUG'] ?? false),
    true,
    true
);

// grab the original error handler
$defaultErrorHandler = $errorMiddleware->getDefaultErrorHandler();

// override it with a wrapper that first sends to Sentry
$errorMiddleware->setDefaultErrorHandler(function (
    ServerRequestInterface $request,
    Throwable             $exception,
    bool                  $displayErrorDetails,
    bool                  $logErrors,
    bool                  $logErrorDetails
) use ($defaultErrorHandler) {
    // 1) capture in Sentry
    \Sentry\captureException($exception);

    // 2) delegate back to Slim’s built-in handler
    return $defaultErrorHandler(
        $request,
        $exception,
        $displayErrorDetails,
        $logErrors,
        $logErrorDetails
    );
});

// 8) Define your routes
$app->get('/', function ($request, $response) {
    $response->getBody()->write('Hello, Slim is installed!');
    return $response;
});

// test route that throws
$app->get('/error-test', function ($request, $response) {
    throw new \RuntimeException('Testing Sentry capture!');
});

// 9) Run
$app->run();
