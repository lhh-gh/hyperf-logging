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

namespace PhpGo\HyperfLogging\Queue;

use Hyperf\AsyncQueue\JobInterface;

interface JobContextExtractorInterface
{
    /** @return array{trace_id?: string, job_id?: string} */
    public function extract(JobInterface $job): array;
}
