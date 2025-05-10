<?php
declare(strict_types=1);

namespace BIMS\Core;

use Dotenv\Dotenv;
use Psr\Log\LoggerInterface;
use Psr\Container\ContainerInterface;
use Throwable;


class Bootstrap
{
    private ContainerInterface $container;
    private LoggerInterface $logger;

    public function __construct(string $projectRoot)
    {
        $this->loadEnv($projectRoot);
        $this->container = $this->buildContainer($projectRoot);
        $this->logger = $this->container->get(LoggerInterface::class);

        // Register error & exception handlers
        $this->registerErrorHandlers();

    }

    private function loadEnv(string $root): void
    {
        $dotenv = Dotenv::createImmutable($root);
        $dotenv->safeLoad();
        $dotenv->required(['APP_ENV', 'APP_DEBUG', 'DATABASE_URL'])->notEmpty();
    }

    private function buildContainer(string $root): ContainerInterface
    {
        // Delegate to your existing DI file
        return require $root . '/config/di.php';
    }

private function registerErrorHandlers(): void
{
    // no need for a local $logger; use your methods which call $this->logger
    set_error_handler([$this, 'handlePhpError']);
    set_exception_handler([$this, 'handleException']);
}


    

   public function handlePhpError(int $severity, string $message, string $file, int $line): bool
{
    $this->logger->error('PHP Error', [
        'severity' => $severity,
        'message'  => $message,
        'file'     => $file,
        'line'     => $line,
    ]);

    return false; // allow the default handler to run too
}

public function handleException(Throwable $e): void
{
    $this->logger->critical('Uncaught Exception', [
        'exception' => $e,
    ]);
}



    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
}