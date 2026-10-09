<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace PhpGo\HyperfLogging;

use Hyperf\Context\Context;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use PhpGo\Logging\LogContextProcessor as CoreProcessor;

class LogContextProcessor implements ProcessorInterface
{
    private CoreProcessor $processor;

    public function __construct(
        string $service = 'app',
        string $environment = 'dev',
        private readonly string $userIdContextKey = '',
    ) {
        $this->processor = new CoreProcessor(LogContext::instance(), $service, $environment);
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $record = ($this->processor)($record);
        if ((LogContext::current()['execution_type'] ?? '') === 'http'
            && $this->userIdContextKey !== ''
            && Context::has($this->userIdContextKey)) {
            $userId = Context::get($this->userIdContextKey);
            if (is_int($userId) || is_string($userId)) {
                $record->extra['user_id'] = $userId;
            }
        }
        return $record;
    }
}
