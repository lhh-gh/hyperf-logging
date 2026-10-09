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
use PhpGo\HyperfLogging\LoggerConfiguration;

use function Hyperf\Support\env;

return LoggerConfiguration::build(
    filename: BASE_PATH . '/runtime/logs/app.log',
    service: (string) env('APP_NAME', 'app'),
    environment: (string) env('APP_ENV', 'dev'),
    output: (string) env('LOG_OUTPUT', 'stdout'),
    level: (string) env('LOG_LEVEL', 'INFO'),
    maxFiles: (int) env('LOG_MAX_FILES', 14),
    userIdContextKey: (string) env('LOG_USER_ID_CONTEXT_KEY', ''),
);
