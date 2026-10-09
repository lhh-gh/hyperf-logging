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

namespace PhpGo\HyperfLogging\Crontab;

use Closure;
use Hyperf\Crontab\Crontab;
use Hyperf\Crontab\Strategy\Executor;
use Hyperf\Logger\LoggerFactory;
use PhpGo\HyperfLogging\LogContext;
use Throwable;

class LoggingExecutor extends Executor
{
    protected function catchToExecute(Crontab $crontab, ?Closure $runnable): Closure
    {
        $execute = parent::catchToExecute($crontab, $runnable);

        return static fn () => LogContext::run('crontab', $execute, [
            'task' => $crontab->getName(),
            'started_ns' => hrtime(true),
        ]);
    }

    protected function logResult(Crontab $crontab, bool $isSuccess, ?Throwable $throwable = null)
    {
        $write = function () use ($crontab, $isSuccess, $throwable): void {
            $started = LogContext::current()['started_ns'] ?? null;
            $data = [
                'task' => $crontab->getName(),
                'succeeded' => $isSuccess,
                'duration_ms' => $started === null ? null : round((hrtime(true) - $started) / 1e6, 2),
            ];
            if ($throwable !== null) {
                $data['exception'] = $throwable;
            }

            $event = $isSuccess ? 'crontab.completed' : ($throwable === null ? 'crontab.not_executed' : 'crontab.failed');
            $level = $isSuccess ? 'info' : ($throwable === null ? 'warning' : 'error');
            $this->container->get(LoggerFactory::class)->get('crontab', 'default')->log($level, $event, $data);
        };

        if ((LogContext::current()['execution_type'] ?? '') === 'crontab') {
            $write();
        } else {
            // 关闭等路径没有开始执行，不伪造耗时。
            LogContext::run('crontab', $write, ['task' => $crontab->getName()]);
        }
    }
}
