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

return (function (): ContainerInterface {
    $env = $_ENV['APP_ENV'] ?? 'production';
    $builder = new ContainerBuilder();

    if ($env === 'production') {
        $builder->enableCompilation(__DIR__ . '/../var/cache');
    }

    $builder->addDefinitions([
        LoggerInterface::class => function (ContainerInterface $c) {
            $debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL);
            $logDir = dirname(__DIR__) . '/logs';
            if (! is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }

            $logger = new Logger('bims-core');

            //
            // ─── JSON HANDLER: 7-day rotation ─────────────────────────────────────
            //
            $jsonHandler = new RotatingFileHandler(
                "$logDir/bims.json",
                7,
                $debug ? Logger::DEBUG : Logger::INFO
            );
            $jsonFmt = new JsonFormatter(
                batchMode: JsonFormatter::BATCH_MODE_NEWLINES,
                appendNewline: true,
                ignoreEmptyContextAndExtra: false,
                includeStacktraces: false
            );
            $jsonFmt->setDateFormat('c');
            $jsonFmt->setJsonPrettyPrint(true);
            $jsonHandler->setFormatter($jsonFmt);
            $logger->pushHandler($jsonHandler);

            //
            // ─── TEXT HANDLER: single debug.log (only in debug) ────────────────
            //
            if ($debug) {
                $textHandler = new StreamHandler(
                    "$logDir/debug.log",
                    Logger::DEBUG
                );
                // default line format, ISO-8601 timestamp
                $lineFmt = new LineFormatter(
                    format: null,
                    dateFormat: 'c',
                    allowInlineLineBreaks: true,
                    ignoreEmptyContextAndExtra: true
                );
                $textHandler->setFormatter($lineFmt);
                $logger->pushHandler($textHandler);
            }

            // ─── COMMON PROCESSORS ────────────────────────────────────────────
            $logger->pushProcessor(new UidProcessor());
            $logger->pushProcessor(new WebProcessor());

            return $logger;
        },
    ]);

    return $builder->build();
})();
