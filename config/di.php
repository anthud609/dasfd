<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\UidProcessor;
use Monolog\Processor\WebProcessor;
use BIMS\Core\Middleware\CorrelationMiddleware;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;

// Import your AdvancedErrorHandler:
use BIMS\Core\Error\AdvancedErrorHandler;

return (function (): ContainerInterface {
    $env     = $_ENV['APP_ENV'] ?? 'production';
    $builder = new ContainerBuilder();

    if ($env === 'production') {
        $builder->enableCompilation(__DIR__ . '/../var/cache');
    }

    // Define all services in a single array:
    $builder->addDefinitions([

        //
        // ─── Guzzle HTTP Client with Logging ────────────────────────────
        //
        'HttpClient' => function (ContainerInterface $c) {
            $stack = HandlerStack::create();
            // Log full request/response at DEBUG level
            $stack->push(Middleware::log(
                $c->get(LoggerInterface::class),
                new MessageFormatter(MessageFormatter::DEBUG)
            ));
            return new Client(['handler' => $stack]);
        },

        //
        // ─── AdvancedErrorHandler ───────────────────────────────────────
        //
        AdvancedErrorHandler::class => function (ContainerInterface $c) {
            return new AdvancedErrorHandler(
                $c->get(LoggerInterface::class)
            );
        },

        //
        // ─── Audit Logger (separate channel) ────────────────────────────
        //
        'AuditLogger' => function (ContainerInterface $c) {
            $log = new Logger('audit');
            $handler = new RotatingFileHandler(
                __DIR__ . '/../logs/audit.log',
                30,
                Logger::INFO
            );
            $handler->setFormatter(new JsonFormatter());
            $log->pushHandler($handler);
            return $log;
        },

        //
        // ─── Debug Logger (tail-only) ───────────────────────────────────
        //
        'DebugLogger' => function (ContainerInterface $c) {
            $log = new Logger('debug');
            $handler = new StreamHandler(
                __DIR__ . '/../logs/debug-tail.log',
                Logger::DEBUG
            );
            $handler->setFormatter(new LineFormatter(
                format:      null,
                dateFormat:  'c',
                allowInlineLineBreaks: true,
                ignoreEmptyContextAndExtra: true
            ));
            $log->pushHandler($handler);
            return $log;
        },

        //
        // ─── Correlation-ID Middleware ──────────────────────────────────
        //
        CorrelationMiddleware::class => function (ContainerInterface $c) {
            return new CorrelationMiddleware(
                $c->get(LoggerInterface::class)
            );
        },

        //
        // ─── Primary PSR-3 Logger ────────────────────────────────────────
        //
        LoggerInterface::class => function (ContainerInterface $c) {
            $debug  = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL);
            $logDir = dirname(__DIR__) . '/logs';

            if (! is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }

            $logger = new Logger('bims-core');

            // JSON handler: 7-day rotation
            $jsonHandler = new RotatingFileHandler(
                "$logDir/bims.json",
                7,
                $debug ? Logger::DEBUG : Logger::INFO
            );
            $jsonFmt = new JsonFormatter(
                batchMode:                     JsonFormatter::BATCH_MODE_NEWLINES,
                appendNewline:                 true,
                ignoreEmptyContextAndExtra:    false,
                includeStacktraces:            false
            );
            $jsonFmt->setDateFormat('c');
            $jsonFmt->setJsonPrettyPrint(true);
            $jsonHandler->setFormatter($jsonFmt);
            $logger->pushHandler($jsonHandler);

            // Text handler (debug only)
            if ($debug) {
                $textHandler = new StreamHandler(
                    "$logDir/debug.log",
                    Logger::DEBUG
                );
                $lineFmt = new LineFormatter(
                    format:      null,
                    dateFormat:  'c',
                    allowInlineLineBreaks: true,
                    ignoreEmptyContextAndExtra: true
                );
                $textHandler->setFormatter($lineFmt);
                $logger->pushHandler($textHandler);
            }

            // Common processors
            $logger->pushProcessor(new UidProcessor());
            $logger->pushProcessor(new WebProcessor());

            return $logger;
        },
    ]);

    // Build the container
    $container = $builder->build();

    // ─── Optional Shutdown handler for fatal errors ─────────────────────
    // (You can remove this if your AdvancedErrorHandler already does it.)
    register_shutdown_function(function () use ($container) {
        $err = error_get_last();
        if (
            $err
            && in_array($err['type'], [
                E_ERROR,
                E_PARSE,
                E_CORE_ERROR,
                E_COMPILE_ERROR
            ], true)
        ) {
            $container
                ->get(LoggerInterface::class)
                ->critical('Fatal shutdown error', $err);
        }
    });

    return $container;
})();
