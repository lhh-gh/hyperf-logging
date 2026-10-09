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

use Hyperf\Context\ApplicationContext;
use PhpGo\Logging\LogContext as ExecutionContext;

/** Convenience facade for Hyperf; other runtimes use the core instance directly. */
class LogContext
{
    public const KEY = ExecutionContext::KEY;

    public static function instance(): ExecutionContext
    {
        return ApplicationContext::getContainer()->get(ExecutionContext::class);
    }

    public static function current(): array
    {
        return self::instance()->current();
    }

    public static function traceId(string $candidate = ''): string
    {
        return ExecutionContext::traceId($candidate);
    }

    public static function enter(string $type, array $fields = []): ?array
    {
        return self::instance()->enter($type, $fields);
    }

    public static function restore(?array $previous): void
    {
        self::instance()->restore($previous);
    }

    public static function run(string $type, callable $callback, array $fields = []): mixed
    {
        return self::instance()->run($type, $callback, $fields);
    }

    public static function forJob(): array
    {
        return self::instance()->forJob();
    }
}
