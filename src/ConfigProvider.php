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

use Hyperf\AsyncQueue\Event\BeforeHandle;
use Hyperf\Database\Events\QueryExecuted;
use PhpGo\HyperfLogging\Context\HyperfContextStorage;
use PhpGo\HyperfLogging\Listener\AccessLogListener;
use PhpGo\HyperfLogging\Listener\DbQueryExecutedListener;
use PhpGo\HyperfLogging\Listener\QueueHandleListener;
use PhpGo\HyperfLogging\Queue\DefaultJobContextExtractor;
use PhpGo\HyperfLogging\Queue\JobContextExtractorInterface;
use PhpGo\Logging\Contract\ContextStorageInterface;

final class ConfigProvider
{
    public function __invoke(): array
    {
        $dependencies = [ContextStorageInterface::class => HyperfContextStorage::class];
        $listeners = [AccessLogListener::class];

        if (class_exists(BeforeHandle::class)) {
            $dependencies[JobContextExtractorInterface::class] = DefaultJobContextExtractor::class;
            $listeners[] = QueueHandleListener::class;
        }
        if (class_exists(QueryExecuted::class)) {
            $listeners[] = DbQueryExecutedListener::class;
        }

        return [
            'dependencies' => $dependencies,
            'listeners' => $listeners,
            // Defaults are published, not merged recursively into application scalar options.
            'publish' => [
                [
                    'id' => 'logger',
                    'description' => 'Structured JSON logger configuration.',
                    'source' => __DIR__ . '/../publish/logger.php',
                    'destination' => BASE_PATH . '/config/autoload/logger.php',
                ],
                [
                    'id' => 'logging',
                    'description' => 'Slow query logging configuration.',
                    'source' => __DIR__ . '/../publish/logging.php',
                    'destination' => BASE_PATH . '/config/autoload/logging.php',
                ],
            ],
        ];
    }
}
