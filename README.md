# phpgo/hyperf-logging

为 [phpgo/logging](https://github.com/lhh-gh/logging) 提供 Hyperf 3.1 适配：协程上下文、HTTP 请求日志、队列生命周期、慢查询、定时任务和 JSON Logger 配置。包内不引用应用的 `App\` 类，不定义业务错误码、响应 JSON 协议、订单或通知逻辑。

要求 PHP >= 8.1、Hyperf `~3.1.0`、Monolog ^3.1。队列、数据库和定时任务组件是可选依赖，启用时也要求 `~3.1.0`。目前尚未验证 Hyperf 3.2，因此 Composer 明确限制版本；当前仓库实际运行版本以 `composer.lock` 为准。

## 安装与注册

```bash
composer require phpgo/hyperf-logging:^0.1
php bin/hyperf.php vendor:publish phpgo/hyperf-logging
```

Composer 会自动安装核心包 `phpgo/logging`。已有配置文件时合并 [publish/logger.php](publish/logger.php) 和 [publish/logging.php](publish/logging.php)，保留目标项目的其他配置。部署时使用项目的 `composer.lock` 安装依赖。

### 本地开发

需要同时修改两个包时，分别克隆到目标 Hyperf 项目的 `packages/logging` 和 `packages/hyperf-logging`，合并配置：

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/*",
            "options": {
                "symlink": true,
                "versions": {
                    "phpgo/logging": "0.1.x-dev",
                    "phpgo/hyperf-logging": "0.1.x-dev"
                }
            }
        }
    ],
    "require": {
        "phpgo/logging": "^0.1@dev",
        "phpgo/hyperf-logging": "^0.1@dev"
    }
}
```

```bash
composer update phpgo/logging phpgo/hyperf-logging --no-scripts
php bin/hyperf.php vendor:publish phpgo/hyperf-logging
```

使用 path 仓库部署时，两个本地包必须随代码交付；生产构建可配置 `symlink: false`。

### 自动注册

Hyperf 通过 Composer `extra.hyperf.config` 自动发现 `ConfigProvider`。它注册协程存储和 HTTP Listener；只有安装对应依赖时才注册队列与慢查询 Listener。包内 Listener 不使用 `#[Listener]`，无需再在应用的 `listeners.php` 中重复注册。

Provider 不默认合并 `logger`、`logging` 的标量选项，避免 Hyperf 3.1 的 `array_merge_recursive` 把应用配置变为数组。Middleware、全局 Logger 和 Crontab 绑定由接入方显式配置。

## Logger 与 HTTP 接入

`logger.php` 的配置生成器接受文件路径、服务名、环境、输出、级别、保留文件数和用户上下文键。返回普通 Hyperf 配置数组，可继续增添分组、Handler、Formatter 或 Processor。

发布模板默认输出 stdout，默认不读取任何鉴权上下文。需要附加用户身份时设置 `LOG_USER_ID_CONTEXT_KEY=auth.user_id`，由鉴权代码在验证成功后写入该键。支持字符串或整数用户 ID，只在 HTTP 作用域中读取；空键禁用该功能。

把以下中间件放在 `config/autoload/middlewares.php` 的 HTTP 列表首位：

```php
use PhpGo\HyperfLogging\Middleware\TraceContextMiddleware;

return [
    'http' => [
        TraceContextMiddleware::class,
        // 保留已有中间件。
    ],
];
```

同时在 `config/autoload/server.php` 对应 HTTP 服务的 `options` 中设置 `'enable_request_lifecycle' => true`。成功路径附加 `X-Trace-Id`；`AccessLogListener` 在 `RequestHandled` 读取最终状态。上下文保留到这个事件结束，由 Hyperf 的请求协程生命周期释放，不能在 Middleware 返回前清空。

业务异常仍由目标项目的异常处理器映射。需要记录业务码时调用 `HttpLogContext::setBusinessCode($code)`；异常响应使用 `HttpLogContext::TRACE_HEADER` 回传 `LogContext::current()['trace_id']`。早于中间件的异常可先用 `LogContext::enter('http')` 建立 ID；没有开始时间时耗时保持 null。未知异常只在最终处理出口记录一次堆栈。

可在 `dependencies.php` 选择把 PSR-3 和框架控制台日志接入同一配置：

```php
use Hyperf\Contract\StdoutLoggerInterface;
use PhpGo\HyperfLogging\Factory\DefaultLoggerFactory;
use Psr\Log\LoggerInterface;

return [
    LoggerInterface::class => DefaultLoggerFactory::class,
    StdoutLoggerInterface::class => DefaultLoggerFactory::class,
];
```

业务类通过构造函数注入 `LoggerInterface`，或注入 Hyperf 的 `LoggerFactory` 选择有限的 channel 名称。本包不注册全局 `logger()` 函数。

## 执行上下文

```php
use PhpGo\HyperfLogging\LogContext;

$result = LogContext::run('command', static function () use ($logger): int {
    $logger->info('import.completed');
    return 0;
}, ['task' => 'catalog:import']);
```

常驻进程每轮工作建立独立作用域。子协程、下游请求和消息只显式传播 trace ID，不复制整个请求 Context。`PhpGo\Logging\LogContext` 也可直接构造注入；Provider 为它提供协程隔离的 `ContextStorageInterface`。

## 队列扩展

手写 Job 实现 `PhpGo\Logging\Contract\LogAwareJobInterface`，在构造时保存 `LogContext::forJob()` 的结果，通过 `logContext(): array` 返回。保持元数据在序列化消息中，重试复用相同的 job ID。

注解队列用参数标记表达元数据的位置，不绑定某个业务类、方法名或参数下标：

```php
use Hyperf\AsyncQueue\Annotation\AsyncQueueMessage;
use PhpGo\Logging\Attribute\JobLogContext;
use Psr\Log\LoggerInterface;

class CatalogService
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    #[AsyncQueueMessage(maxAttempts: 3)]
    public function rebuild(string $catalogId, int $batchSize, #[JobLogContext] array $metadata): void
    {
        $this->logger->info('catalog.rebuilt', ['catalog_id' => $catalogId]);
    }
}
```

通过容器获取服务以启用 AOP，再调用 `$service->rebuild('catalog-1', 100, LogContext::forJob())`。一个方法只标记一个非可变参数；支持位置参数和命名参数。参数标记不自动注入元数据，投递方仍需显式传值。

旧消息的类名、方法和数组参数不需要更改，只需在相应方法参数上增加标记。无标记消息不会按参数位置猜测；仍有独立 execution ID，但不保证跨重试关联。监听器只传播字符串 `trace_id`、`job_id`，执行次数来自队列驱动，不信任消息中的 `attempt`。

有特殊消息格式时实现 `Queue\JobContextExtractorInterface`，在应用 `dependencies.php` 中把该接口绑定到自己的实现。默认实现是 `Queue\DefaultJobContextExtractor`；自定义提取器的输出同样经过关联 ID 白名单。

Hyperf 3.1 的 `maxAttempts: 3` 表示首次加 3 次重试，最多 4 次执行。终止事件恢复前一作用域。`queue.handled` 的 result 为框架返回值，包含 `ACK`、`RETRY`、`REQUEUE`、`DROP`，不直接等于业务成功。

## 慢查询与定时任务

安装 `hyperf/database` 时自动注册慢查询监听，阈值 `logging.slow_query_ms` 默认 200 毫秒。仅记录连接名、耗时、SQL 哈希，不展开 bindings 或输出原始 SQL。

安装 `hyperf/crontab` 后，可显式增加绑定：

```php
use Hyperf\Crontab\Strategy\Executor;
use PhpGo\HyperfLogging\Crontab\LoggingExecutor;

return [Executor::class => LoggingExecutor::class];
```

该绑定只扩展执行日志；任务、调度进程和互斥存储按 Hyperf 配置。升级框架后需要验证 Executor 的受保护扩展点，不直接放宽 Composer 版本限制。

## 验证与运行边界

克隆本包仓库后，在包根目录运行：

```bash
composer install
composer test
```

包测试使用内存日志和序列化消息，不连接外部数据库或 Redis。使用前述本地 path 仓库开发时，也可在目标项目根目录执行 `vendor/bin/phpunit --configuration packages/hyperf-logging/phpunit.xml.dist`。实际协程环境仍需 Linux/WSL/容器及所选 Hyperf 引擎。

本包不启动 Redis、数据库、日志采集器、调度器或告警服务。文件/stdout 的容量、采集重试和审计可靠性由部署系统负责。Handler 写入失败继续传播；Processor 不清洗任意业务 context 或异常文本。

版本变更见 [CHANGELOG.md](CHANGELOG.md)。许可证：[MIT](LICENSE)。
