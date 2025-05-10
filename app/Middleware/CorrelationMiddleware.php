<?php
declare(strict_types=1);

namespace BIMS\Core\Middleware;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class CorrelationMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 1. Extract or generate
        $correlationId = $request->getHeaderLine('X-Correlation-Id');
        if (! $correlationId) {
            $correlationId = bin2hex(random_bytes(16));
        }

        // 2. Start timer
        $start = microtime(true);

        // 3. Attach to request attributes
        $request = $request
            ->withAttribute('correlation_id', $correlationId)
            ->withAttribute('start_time', $start);

        // 4. Push a processor to include it on every record
        $this->logger->pushProcessor(function(array $record) use ($correlationId) {
            $record['extra']['correlation_id'] = $correlationId;
            return $record;
        });

        // 5. Handle next middleware/route
        $response = $handler->handle($request);

        // 6. Compute elapsed & status
        $duration = microtime(true) - $start;
        $status   = $response->getStatusCode();

        // 7. Log summary line
        $this->logger->info('Request completed', [
            'status'      => $status,
            'duration_ms' => (int) ($duration * 1000),
        ]);

        // 8. Return response + header
        return $response->withHeader('X-Correlation-Id', $correlationId);
    }
}
