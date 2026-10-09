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

namespace PhpGo\HyperfLogging\Tests;

use Hyperf\AsyncQueue\AnnotationJob;
use PhpGo\HyperfLogging\Queue\DefaultJobContextExtractor;
use PhpGo\HyperfLogging\Tests\Fixtures\ContextJob;
use PhpGo\HyperfLogging\Tests\Fixtures\MetadataService;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
final class DefaultJobContextExtractorTest extends TestCase
{
    public function testMarkedParametersWorkAtDifferentPositionsAndWithNamedArguments(): void
    {
        $extractor = new DefaultJobContextExtractor();
        $ids = ['trace_id' => str_repeat('a', 32), 'job_id' => str_repeat('b', 32)];
        $metadata = $ids + ['user_id' => 'do-not-transfer', 'task' => 'do-not-transfer'];
        $cases = [
            ['first', [$metadata, 'payload']],
            ['last', ['payload', 7, $metadata]],
            ['last', ['metadata' => $metadata, 'priority' => 7, 'payload' => 'payload']],
        ];
        foreach ($cases as [$method, $params]) {
            $job = unserialize(serialize(new AnnotationJob(MetadataService::class, $method, $params)));
            $this->assertSame($ids, $extractor->extract($job));
        }
    }

    public function testUnmarkedAndObsoleteAnnotationJobsDoNotGuessMetadata(): void
    {
        $extractor = new DefaultJobContextExtractor();
        $ids = ['trace_id' => str_repeat('a', 32), 'job_id' => str_repeat('b', 32)];
        $this->assertSame([], $extractor->extract(new AnnotationJob(MetadataService::class, 'unmarked', ['payload', $ids])));
        $this->assertSame([], $extractor->extract(new AnnotationJob(MetadataService::class, 'removedMethod', ['payload', $ids])));
        $this->assertSame([], $extractor->extract(new AnnotationJob('MissingJobService', 'send', [$ids])));
        $this->assertSame([], $extractor->extract(new AnnotationJob(MetadataService::class, 'first', ['not-an-array', 'payload'])));
    }

    public function testJobContractSurvivesSerializationAndRejectsNonStringIds(): void
    {
        $extractor = new DefaultJobContextExtractor();
        $job = unserialize(serialize(new ContextJob([
            'trace_id' => str_repeat('a', 32),
            'job_id' => ['invalid'],
            'attempt' => 999,
            'user_id' => 42,
        ])));
        $this->assertSame(['trace_id' => str_repeat('a', 32)], $extractor->extract($job));
    }
}
