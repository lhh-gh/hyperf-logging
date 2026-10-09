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

namespace PhpGo\HyperfLogging\Tests\Fixtures;

use Hyperf\AsyncQueue\Job;
use PhpGo\Logging\Contract\LogAwareJobInterface;

final class ContextJob extends Job implements LogAwareJobInterface
{
    public function __construct(private array $metadata)
    {
    }

    public function logContext(): array
    {
        return $this->metadata;
    }

    public function handle(): void
    {
    }
}
