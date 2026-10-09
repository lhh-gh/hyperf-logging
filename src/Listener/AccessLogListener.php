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

namespace PhpGo\HyperfLogging\Listener;

use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\HttpServer\Event\RequestHandled;
use Hyperf\HttpServer\Router\Dispatched;
use Hyperf\Logger\LoggerFactory;
use PhpGo\HyperfLogging\Context\HttpLogContext;
use Psr\Log\LoggerInterface;

class AccessLogListener implements ListenerInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerFactory $factory)
    {
        $this->logger = $factory->get('access', 'default');
    }

    public function listen(): array
    {
        return [RequestHandled::class];
    }

    public function process(object $event): void
    {
        if (! $event instanceof RequestHandled) {
            return;
        }

        $route = $event->request?->getAttribute(Dispatched::class);
        $this->logger->info('http.request_completed', [
            'method' => $event->request?->getMethod(),
            'route' => $route?->handler?->route ?? '__unmatched__',
            'http_status' => $event->response?->getStatusCode(),
            'business_code' => HttpLogContext::businessCode($event->exception === null ? 0 : null),
            'duration_ms' => HttpLogContext::durationMs(),
        ]);
    }
}
