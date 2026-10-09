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

namespace PhpGo\HyperfLogging\Listener;

use Hyperf\AsyncQueue\Event\AfterHandle;
use Hyperf\AsyncQueue\Event\BeforeHandle;
use Hyperf\AsyncQueue\Event\FailedHandle;
use Hyperf\AsyncQueue\Event\RetryHandle;
use Hyperf\AsyncQueue\JobInterface;
use Hyperf\Context\Context;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Logger\LoggerFactory;
use PhpGo\HyperfLogging\LogContext;
use PhpGo\HyperfLogging\Queue\JobContextExtractorInterface;
use Psr\Log\LoggerInterface;

class QueueHandleListener implements ListenerInterface
{
    private const STACK = 'logging.queue_stack';

    private LoggerInterface $logger;

    public function __construct(LoggerFactory $factory, private readonly JobContextExtractorInterface $extractor)
    {
        $this->logger = $factory->get('queue', 'default');
    }

    public function listen(): array
    {
        return [
            AfterHandle::class,
            BeforeHandle::class,
            FailedHandle::class,
            RetryHandle::class,
        ];
    }

    public function process(object $event): void
    {
        if ($event instanceof BeforeHandle) {
            $message = $event->getMessage();
            $fields = $this->metadata($message->job());
            // 驱动在失败后递增计数，因此在执行开始时固定本次尝试号。
            $fields['attempt'] = $message->getAttempts() + 1;
            $stack = Context::get(self::STACK, []);
            $stack[] = [
                'previous' => LogContext::enter('queue', $fields),
                'started_ns' => hrtime(true),
            ];
            Context::set(self::STACK, $stack);
            $this->logger->debug('queue.started', ['job_class' => $message->job()::class]);
            return;
        }

        if (! $event instanceof AfterHandle && ! $event instanceof RetryHandle && ! $event instanceof FailedHandle) {
            return;
        }

        $stack = Context::get(self::STACK, []);
        $scope = array_pop($stack);
        try {
            $data = [
                'job_class' => $event->getMessage()->job()::class,
                'duration_ms' => $scope === null ? null : round((hrtime(true) - $scope['started_ns']) / 1e6, 2),
            ];

            if ($event instanceof FailedHandle) {
                $this->logger->error('queue.failed', $data + ['exception' => $event->getThrowable()]);
            } elseif ($event instanceof RetryHandle) {
                $this->logger->warning('queue.retry', $data + ['exception' => $event->getThrowable()]);
            } else {
                $this->logger->info('queue.handled', $data + ['result' => $event->result->name]);
            }
        } finally {
            if ($scope !== null) {
                LogContext::restore($scope['previous']);
            }
            if ($stack === []) {
                Context::destroy(self::STACK);
            } else {
                Context::set(self::STACK, $stack);
            }
        }
    }

    private function metadata(JobInterface $job): array
    {
        // 自定义提取器也只能传播关联 ID，不携带请求状态。
        return array_filter(
            array_intersect_key($this->extractor->extract($job), array_flip(['trace_id', 'job_id'])),
            static fn (mixed $value): bool => is_string($value),
        );
    }
}
