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

namespace PhpGo\HyperfLogging\Factory;

use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class DefaultLoggerFactory
{
    public function __construct(private readonly string $name = 'app', private readonly string $group = 'default')
    {
    }

    public function __invoke(ContainerInterface $container): LoggerInterface
    {
        return $container->get(LoggerFactory::class)->get($this->name, $this->group);
    }
}
