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

use Hyperf\Config\Config;
use Hyperf\Context\ApplicationContext;
use Hyperf\Context\Context;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSource;
use Hyperf\Logger\Logger;
use Hyperf\Logger\LoggerFactory;
use Monolog\Handler\TestHandler;
use PhpGo\HyperfLogging\ConfigProvider;
use PhpGo\HyperfLogging\Context\HttpLogContext;
use PhpGo\HyperfLogging\LogContext;
use PhpGo\HyperfLogging\LogContextProcessor;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;

abstract class AdapterTestCase extends TestCase
{
    private const CONTEXT_KEYS = [LogContext::KEY, HttpLogContext::STARTED, HttpLogContext::BUSINESS_CODE, 'logging.queue_stack', 'actor.id', 'auth.user_id', ResponseInterface::class];

    protected Container $container;

    protected LoggerFactory $factory;

    protected TestHandler $logs;

    private ContainerInterface $previousContainer;

    private array $previousContext = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = ApplicationContext::getContainer();
        foreach (self::CONTEXT_KEYS as $key) {
            if (Context::has($key)) {
                $this->previousContext[$key] = Context::get($key);
            }
            Context::destroy($key);
        }
        $provider = (new ConfigProvider())();
        $this->container = new Container(new DefinitionSource($provider['dependencies']));
        ApplicationContext::setContainer($this->container);
        $this->container->set(ConfigInterface::class, new Config(['listeners' => $provider['listeners']]));
        $this->logs = new TestHandler();
        $this->factory = $this->createMock(LoggerFactory::class);
        $this->factory->method('get')->willReturnCallback(
            fn (string $name = 'app') => new Logger($name, [$this->logs], [new LogContextProcessor('consumer', 'testing')])
        );
        $this->container->set(LoggerFactory::class, $this->factory);
    }

    protected function tearDown(): void
    {
        foreach (self::CONTEXT_KEYS as $key) {
            Context::destroy($key);
        }
        foreach ($this->previousContext as $key => $value) {
            Context::set($key, $value);
        }
        ApplicationContext::setContainer($this->previousContainer);
        parent::tearDown();
    }
}
