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

use Hyperf\Contract\ConfigInterface;
use Hyperf\Database\Events\QueryExecuted;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

class DbQueryExecutedListener implements ListenerInterface
{
    private LoggerInterface $logger;

    private float $thresholdMs;

    public function __construct(LoggerFactory $factory, ConfigInterface $config)
    {
        $this->logger = $factory->get('sql', 'default');
        $this->thresholdMs = max(1.0, (float) $config->get('logging.slow_query_ms', 200));
    }

    public function listen(): array
    {
        return [
            QueryExecuted::class,
        ];
    }

    public function process(object $event): void
    {
        if (! $event instanceof QueryExecuted || $event->time === null || $event->time < $this->thresholdMs) {
            return;
        }

        $this->logger->warning('db.slow_query', [
            'connection' => $event->connectionName,
            'duration_ms' => round($event->time, 2),
            'query_hash' => hash('sha256', $event->sql),
        ]);
    }
}
