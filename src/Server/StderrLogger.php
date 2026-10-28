<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Server;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

/**
 * The only logger ADVISOR uses. On stdio, stdout belongs to the protocol, so
 * every log line goes to stderr (best practice 1.3).
 */
final class StderrLogger extends AbstractLogger
{
    private const ORDER = [
        LogLevel::DEBUG => 0,
        LogLevel::INFO => 1,
        LogLevel::NOTICE => 2,
        LogLevel::WARNING => 3,
        LogLevel::ERROR => 4,
        LogLevel::CRITICAL => 5,
        LogLevel::ALERT => 6,
        LogLevel::EMERGENCY => 7,
    ];

    /** @var resource */
    private $stream;

    /**
     * @param resource|null $stream defaults to STDERR
     */
    public function __construct(private readonly string $threshold = LogLevel::WARNING, $stream = null)
    {
        $this->stream = $stream ?? STDERR;
    }

    /**
     * @param mixed                $level
     * @param array<string, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $level = (string) $level;
        if ((self::ORDER[$level] ?? 3) < (self::ORDER[$this->threshold] ?? 3)) {
            return;
        }
        $detail = '';
        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $detail = ' (' . $context['exception']::class . ': ' . $context['exception']->getMessage() . ')';
        }
        fwrite($this->stream, sprintf("[advisor %s] %s%s\n", $level, (string) $message, $detail));
    }
}
