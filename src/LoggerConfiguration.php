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

use InvalidArgumentException;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

final class LoggerConfiguration
{
    public static function build(
        string $filename,
        string $service = 'app',
        string $environment = 'dev',
        string $output = 'stdout',
        string $level = 'INFO',
        int $maxFiles = 14,
        string $userIdContextKey = '',
    ): array {
        if (! in_array($output, ['daily', 'stdout'], true)) {
            throw new InvalidArgumentException('LOG_OUTPUT must be daily or stdout');
        }

        $monologLevel = Logger::toMonologLevel($level);
        $common = [
            'formatter' => [
                'class' => JsonFormatter::class,
                'constructor' => [
                    'appendNewline' => true,
                    'includeStacktraces' => true,
                ],
            ],
            'processors' => [
                [
                    'class' => LogContextProcessor::class,
                    'constructor' => compact('service', 'environment', 'userIdContextKey'),
                ],
            ],
        ];

        $daily = array_merge($common, [
            'handler' => [
                'class' => RotatingFileHandler::class,
                'constructor' => [
                    'filename' => $filename,
                    'maxFiles' => max(1, $maxFiles),
                    'level' => $monologLevel,
                    'useLocking' => false,
                ],
            ],
        ]);
        $stdout = array_merge($common, [
            'handler' => [
                'class' => StreamHandler::class,
                'constructor' => [
                    'stream' => 'php://stdout',
                    'level' => $monologLevel,
                    'useLocking' => false,
                ],
            ],
        ]);

        return [
            'default' => $output === 'stdout' ? $stdout : $daily,
            'daily' => $daily,
            'stdout' => $stdout,
        ];
    }
}
