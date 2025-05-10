<?php
declare(strict_types=1);

namespace BIMS\Core\Middleware;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Monolog\LogRecord;

class CorrelationMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 1) Extract or generate
        $correlationId = $request->getHeaderLine('X-Correlation-Id') 
            ?: bin2hex(random_bytes(16));

        // 2) Start timer
        $start = microtime(true);

        // 3) Attach to request so downstream can read it if needed
        $request = $request
            ->withAttribute('correlation_id', $correlationId)
            ->withAttribute('start_time', $start);

        // 4) Push a Monolog v3 processor that accepts/returns LogRecord
        $this->logger->pushProcessor(function (LogRecord $record) use ($correlationId): LogRecord {
            $record->extra['correlation_id'] = $correlationId;
            return $record;
        });

        // 5) Continue down the middleware stack
        $response = $handler->handle($request);

        // 6) Compute elapsed time & status code
        $duration = microtime(true) - $start;
        $status   = $response->getStatusCode();

        // 7) Log a summary line (will include correlation_id via the processor)
        $this->logger->info('Request completed', [
            'status'      => $status,
            'duration_ms' => (int) ($duration * 1000),
        ]);

        // 8) Return response with the header echoed back
        return $response->withHeader('X-Correlation-Id', $correlationId);
    }
}
