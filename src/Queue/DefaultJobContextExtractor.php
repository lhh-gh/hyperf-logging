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

use Hyperf\AsyncQueue\AnnotationJob;
use Hyperf\AsyncQueue\JobInterface;
use PhpGo\Logging\Attribute\JobLogContext;
use PhpGo\Logging\Contract\LogAwareJobInterface;
use ReflectionException;
use ReflectionMethod;

final class DefaultJobContextExtractor implements JobContextExtractorInterface
{
    public function extract(JobInterface $job): array
    {
        $metadata = [];
        if ($job instanceof LogAwareJobInterface) {
            $metadata = $job->logContext();
        } elseif ($job instanceof AnnotationJob) {
            $metadata = $this->annotationMetadata($job);
        }

        // Only the two IDs cross the queue boundary, including for custom jobs.
        return array_filter(
            array_intersect_key($metadata, array_flip(['trace_id', 'job_id'])),
            static fn (mixed $value): bool => is_string($value),
        );
    }

    private function annotationMetadata(AnnotationJob $job): array
    {
        try {
            $method = new ReflectionMethod($job->class, $job->method);
        } catch (ReflectionException) {
            // The driver will report an obsolete/invalid job through its normal failure path.
            return [];
        }

        foreach ($method->getParameters() as $parameter) {
            if ($parameter->getAttributes(JobLogContext::class) === []) {
                continue;
            }
            $value = $job->params[$parameter->getName()] ?? $job->params[$parameter->getPosition()] ?? null;
            return is_array($value) ? $value : [];
        }

        return [];
    }
}
