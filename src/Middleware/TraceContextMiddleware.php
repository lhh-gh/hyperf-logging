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

namespace PhpGo\HyperfLogging\Middleware;

use Hyperf\Context\Context;
use PhpGo\HyperfLogging\Context\HttpLogContext;
use PhpGo\HyperfLogging\LogContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TraceContextMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        HttpLogContext::begin();
        LogContext::enter('http', ['trace_id' => $request->getHeaderLine(HttpLogContext::TRACE_HEADER)]);
        $traceId = LogContext::current()['trace_id'];

        // 供后续响应助手及异常处理器使用。
        $response = Context::get(ResponseInterface::class)->withHeader(HttpLogContext::TRACE_HEADER, $traceId);
        Context::set(ResponseInterface::class, $response);

        // 下游可能返回新响应，成功路径仍要显式添加头。
        return $handler->handle($request)->withHeader(HttpLogContext::TRACE_HEADER, $traceId);
    }
}
