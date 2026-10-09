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
use Hyperf\Context\ApplicationContext;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSource;
use PhpGo\HyperfLogging\ConfigProvider;

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__));
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
require_once is_file($autoload) ? $autoload : dirname(__DIR__, 3) . '/vendor/autoload.php';

if (! ApplicationContext::hasContainer()) {
    ApplicationContext::setContainer(new Container(new DefinitionSource((new ConfigProvider())()['dependencies'])));
}
