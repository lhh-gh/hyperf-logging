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

use Hyperf\AsyncQueue\Event\AfterHandle;
use Hyperf\AsyncQueue\Event\BeforeHandle;
use Hyperf\AsyncQueue\JobMessage;
use Hyperf\AsyncQueue\Result;
use Hyperf\Config\Config;
use Hyperf\Context\Context;
use Hyperf\Event\ListenerProviderFactory;
use Hyperf\HttpMessage\Server\Request;
use Hyperf\HttpMessage\Server\Response;
use Hyperf\HttpServer\Event\RequestHandled;
use Hyperf\Logger\Logger;
use Hyperf\Logger\LoggerFactory;
use PhpGo\HyperfLogging\Context\HttpLogContext;
use PhpGo\HyperfLogging\LogContext;
use PhpGo\HyperfLogging\LogContextProcessor;
use PhpGo\HyperfLogging\LoggerConfiguration;
use PhpGo\HyperfLogging\Middleware\TraceContextMiddleware;
use PhpGo\HyperfLogging\Queue\JobContextExtractorInterface;
use PhpGo\HyperfLogging\Tests\Fixtures\ContextJob;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @internal
 * @coversNothing
 */
final class ComponentIntegrationTest extends AdapterTestCase
{
    public function testProviderRegistersOneAccessListenerAndUsesFinalResponse(): void
    {
        $provider = (new ListenerProviderFactory())($this->container);
        Context::set(ResponseInterface::class, new Response());
        $request = (new Request('POST', '/items'))->withHeader(HttpLogContext::TRACE_HEADER, str_repeat('a', 32));
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn((new Response())->withStatus(201));
        $response = (new TraceContextMiddleware())->process($request, $handler);
        HttpLogContext::setBusinessCode(1001);
        $event = new RequestHandled($request, $response);
        $listeners = iterator_to_array($provider->getListenersForEvent($event));
        $this->assertCount(1, $listeners);
        foreach ($listeners as $listener) {
            $listener($event);
        }
        $records = $this->logs->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame('http.request_completed', $records[0]->message);
        $this->assertSame(201, $records[0]->context['http_status']);
        $this->assertSame(1001, $records[0]->context['business_code']);
        $this->assertSame($response->getHeaderLine(HttpLogContext::TRACE_HEADER), $records[0]->extra['trace_id']);
    }

    public function testCustomExtractorCanBeInjectedWithoutTransferringRequestState(): void
    {
        $extractor = $this->createMock(JobContextExtractorInterface::class);
        $extractor->expects($this->once())->method('extract')->willReturn([
            'trace_id' => str_repeat('b', 32),
            'job_id' => 'custom-job-42',
            'attempt' => 999,
            'user_id' => 'must-not-transfer',
            'task' => 'must-not-transfer',
        ]);
        $this->container->set(JobContextExtractorInterface::class, $extractor);
        $provider = (new ListenerProviderFactory())($this->container);
        $message = new JobMessage(new ContextJob([]));
        LogContext::enter('http');
        $parent = LogContext::current();
        foreach ([new BeforeHandle($message), new AfterHandle($message, Result::ACK)] as $event) {
            $listeners = iterator_to_array($provider->getListenersForEvent($event));
            $this->assertCount(1, $listeners);
            foreach ($listeners as $listener) {
                $listener($event);
            }
        }
        $records = $this->logs->getRecords();
        $this->assertCount(2, $records);
        $this->assertSame('queue.handled', $records[1]->message);
        $this->assertSame(str_repeat('b', 32), $records[1]->extra['trace_id']);
        $this->assertSame('custom-job-42', $records[1]->extra['job_id']);
        $this->assertSame(1, $records[1]->extra['attempt']);
        $this->assertArrayNotHasKey('user_id', $records[1]->extra);
        $this->assertArrayNotHasKey('task', $records[1]->extra);
        $this->assertSame($parent, LogContext::current());
        $this->assertFalse(Context::has('logging.queue_stack'));
    }

    public function testUserIdentityIsOptInAndOnlyReadDuringHttpExecution(): void
    {
        $default = $this->factory->get('default');
        $enabled = new Logger('configured', [$this->logs], [new LogContextProcessor('consumer', 'testing', 'actor.id')]);
        Context::set('auth.user_id', 42);
        Context::set('actor.id', 'operator-7');
        LogContext::run('http', static function () use ($default, $enabled): void {
            $default->info('identity.disabled');
            $enabled->info('identity.enabled');
        });
        LogContext::run('queue', static fn () => $enabled->info('identity.background'));
        $records = $this->logs->getRecords();
        $this->assertArrayNotHasKey('user_id', $records[0]->extra);
        $this->assertSame('operator-7', $records[1]->extra['user_id']);
        $this->assertArrayNotHasKey('user_id', $records[2]->extra);
    }

    public function testLoggerConfigurationWorksWithAConsumerSuppliedDirectory(): void
    {
        $directory = sys_get_temp_dir() . '/component-logging-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        try {
            $settings = LoggerConfiguration::build($directory . '/events.log', 'consumer', 'testing', 'daily', 'WARNING');
            $factory = new LoggerFactory($this->container, new Config(['logger' => $settings]));
            $logger = $factory->get('audit');
            LogContext::run('command', static function () use ($logger): void {
                $logger->info('filtered');
                $logger->warning('audit.changed', ['id' => 42]);
            });
            $logger->close();
            $files = glob($directory . '/events-*.log');
            $this->assertCount(1, $files);
            $lines = file($files[0], FILE_IGNORE_NEW_LINES);
            $this->assertCount(1, $lines);
            $record = json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('audit', $record['channel']);
            $this->assertSame('audit.changed', $record['message']);
            $this->assertSame('consumer', $record['extra']['service']);
            $this->assertSame('command', $record['extra']['execution_type']);
            $this->assertSame(['id' => 42], $record['context']);
        } finally {
            foreach (glob($directory . '/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
