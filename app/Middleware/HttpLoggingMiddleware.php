<?php
declare(strict_types=1);

namespace BIMS\Core\Middleware;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Routing\RouteContext;
use RuntimeException;

class HttpLoggingMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Gather request details
        $method      = $request->getMethod();
        $path        = $request->getUri()->getPath();
        $protocol    = $request->getProtocolVersion();
        $headers     = $request->getHeaders();
        $userAgent   = $request->getHeaderLine('User-Agent');
        $bodyString  = (string) $request->getBody();
        $requestSize = strlen($bodyString);

        // Route info (if any) — guard in case routing hasn't run
        $routeName = 'n/a';
        try {
            $routeContext = RouteContext::fromRequest($request);
            $route        = $routeContext->getRoute();
            $routeName    = $route?->getName() ?? 'unnamed';
        } catch (RuntimeException $e) {
            // no route matched yet or routing not executed
            $routeName = 'n/a';
        }

        // Memory at start
        $memStart = memory_get_usage();

        // ① Detailed DEBUG log for incoming request
        $this->logger->debug('Incoming request', [
            'method'        => $method,
            'path'          => $path,
            'protocol'      => $protocol,
            'headers'       => $headers,
            'body_size'     => $requestSize,
            'user_agent'    => $userAgent,
            'route'         => $routeName,
            'memory_start'  => $memStart,
        ]);

        // Dispatch to next middleware/route
        $start    = microtime(true);
        $response = $handler->handle($request);
        $duration = microtime(true) - $start;

        // Gather response details
        $status       = $response->getStatusCode();
        $respBody     = (string) $response->getBody();
        $responseSize = strlen($respBody);
        $memEnd       = memory_get_usage();

        // ② INFO log for outgoing response
        $this->logger->info('Outgoing response', [
            'status'         => $status,
            'duration_ms'    => (int) ($duration * 1000),
            'response_size'  => $responseSize,
            'memory_end'     => $memEnd,
        ]);

        return $response;
    }
}
