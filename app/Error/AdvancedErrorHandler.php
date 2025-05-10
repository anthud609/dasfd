<?php
declare(strict_types=1);

namespace BIMS\Core\Error;

use Psr\Log\LoggerInterface;
use Throwable;

class AdvancedErrorHandler
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Call this once at startup to attach all global handlers.
     */
    public function registerHandlers(): void
    {
        // Make PHP report *all* errors if that’s your goal
        error_reporting(E_ALL);

        set_error_handler([$this, 'handlePhpError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Custom handler for PHP "errors" (warnings/notices/etc).
     */
    public function handlePhpError(int $severity, string $message, string $file, int $line): bool
    {
        // 1) Log with Monolog
        $this->logger->error('PHP Error', [
            'severity' => $severity,
            'message'  => $message,
            'file'     => $file,
            'line'     => $line,
        ]);

        // 2) Also send to Sentry
        \Sentry\captureMessage("PHP Error: $message (Severity: $severity) | $file:$line");

        // Returning FALSE means the default PHP error handler *also* runs
        // If you want to completely handle it yourself, return true.
        return false;
    }

    /**
     * Custom handler for uncaught exceptions.
     */
    public function handleException(Throwable $e): void
    {
        // 1) Log with Monolog
        $this->logger->critical('Uncaught Exception', [
            'exception' => $e,
        ]);

        // 2) Also send to Sentry
        \Sentry\captureException($e);
    }

    /**
     * Last-chance catch for fatal errors on shutdown.
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            // 1) Log with Monolog
            $this->logger->critical('Fatal shutdown error', $error);

            // 2) Also send to Sentry
            \Sentry\captureMessage(
                sprintf('Fatal shutdown error: %s in %s:%d', $error['message'], $error['file'], $error['line'])
            );
        }
    }
}
