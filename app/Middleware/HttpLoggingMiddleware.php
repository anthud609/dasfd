<?php

// app/Middleware/HttpLoggingMiddleware.php
namespace BIMS\Core\Middleware;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class HttpLoggingMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 1) Log the incoming request (method, path, headers, optional body)
        $this->logger->info('Incoming request', [
            'method'  => $request->getMethod(),
            'path'    => (string)$request->getUri()->getPath(),
            'headers' => $request->getHeaders(),
            // if JSON payload:
            'body'    => $request->getParsedBody(),
        ]);

        // 2) Dispatch to next handler
        $response = $handler->handle($request);

        // 3) Log the outgoing response (status + body if small)
        $this->logger->info('Outgoing response', [
            'status' => $response->getStatusCode(),
            'body'   => (string)$response->getBody(),
        ]);

        return $response;
    }
}
