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

namespace PhpGo\HyperfLogging\Context;

use Hyperf\Context\Context;

final class HttpLogContext
{
    public const TRACE_HEADER = 'X-Trace-Id';

    public const STARTED = 'logging.http_started_ns';

    public const BUSINESS_CODE = 'logging.business_code';

    public static function begin(): void
    {
        Context::destroy(self::BUSINESS_CODE);
        Context::set(self::STARTED, hrtime(true));
    }

    public static function durationMs(): ?float
    {
        $started = Context::get(self::STARTED);
        return $started === null ? null : round((hrtime(true) - $started) / 1e6, 2);
    }

    public static function setBusinessCode(int $code): void
    {
        Context::set(self::BUSINESS_CODE, $code);
    }

    public static function businessCode(?int $default = 0): ?int
    {
        return Context::get(self::BUSINESS_CODE, $default);
    }
}
