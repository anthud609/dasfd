<?php
declare(strict_types=1);

namespace BIMS\Core\Processor;

use Monolog\LogRecord;

class UniversalRedactor
{
    /** @var string[] lower-cased header keys to redact */
    private array $keysToRedact;

    public function __construct(array $keys = ['authorization', 'cookie'])
    {
        $this->keysToRedact = array_map('strtolower', $keys);
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        // Only redact headers in the context if present
        if (isset($record->context['headers']) && is_array($record->context['headers'])) {
            foreach ($record->context['headers'] as $key => $value) {
                if (in_array(strtolower($key), $this->keysToRedact, true)) {
                    $record->context['headers'][$key] = ['REDACTED'];
                }
            }
        }

        return $record;
    }
}
