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

use PhpGo\Logging\Attribute\JobLogContext;

final class MetadataService
{
    public function first(#[JobLogContext] array $metadata, string $payload): void
    {
    }

    public function last(string $payload, int $priority, #[JobLogContext] array $metadata): void
    {
    }

    public function unmarked(string $payload, array $metadata): void
    {
    }
}
