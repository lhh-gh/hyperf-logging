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

namespace PhpGo\HyperfLogging\Context;

use Hyperf\Context\Context;
use PhpGo\Logging\Contract\ContextStorageInterface;

final class HyperfContextStorage implements ContextStorageInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        return Context::get($key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        Context::set($key, $value);
    }

    public function delete(string $key): void
    {
        Context::destroy($key);
    }
}
