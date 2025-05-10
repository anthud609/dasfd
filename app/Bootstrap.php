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




    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
}