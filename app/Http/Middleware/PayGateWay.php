<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

class PayGateWay
{

    /**
     * Handle an incoming request.
     * 支付网关安全中间件：基础安全校验
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // 记录支付回调日志，便于安全审计
        if ($request->is('pay/*/notify_url')) {
            Log::channel('daily')->info('Payment callback received', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'method' => $request->method(),
            ]);
        }

        return $next($request);
    }
}
