<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ApiHook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 任务最大尝试次数。
     *
     * @var int
     */
    public $tries = 2;

    /**
     * 任务运行的超时时间。
     *
     * @var int
     */
    public $timeout = 30;

    /**
     * @var Order
     */
    private $order;

    /**
     * 商品服务层.
     * @var \App\Service\PayService
     */
    private $goodsService;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
        $this->goodsService = app('Service\GoodsService');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $goodInfo = $this->goodsService->detail($this->order->goods_id);
        // 判断是否有配置支付回调
        if(empty($goodInfo->api_hook)){
            return;
        }
        // 安全校验：仅允许 http/https 协议，阻止 SSRF 攻击内网
        $parsedUrl = parse_url($goodInfo->api_hook);
        if (!$parsedUrl || !isset($parsedUrl['scheme']) || !in_array(strtolower($parsedUrl['scheme']), ['http', 'https'])) {
            \Illuminate\Support\Facades\Log::warning('ApiHook blocked: invalid URL scheme', ['url' => $goodInfo->api_hook]);
            return;
        }
        $host = $parsedUrl['host'] ?? '';
        // 阻止访问内网地址：解析DNS后使用filter_var验证
        // 覆盖RFC1918、保留地址、云元数据(169.254.x)、IPv6等所有内网变体
        $resolvedIP = gethostbyname($host);
        if ($resolvedIP === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
            // DNS解析失败且不是有效IP，阻止访问
            \Illuminate\Support\Facades\Log::warning('ApiHook blocked: unresolvable host', ['url' => $goodInfo->api_hook]);
            return;
        }
        if (!filter_var($resolvedIP, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            \Illuminate\Support\Facades\Log::warning('ApiHook blocked: internal/reserved IP', ['url' => $goodInfo->api_hook, 'resolved' => $resolvedIP]);
            return;
        }
        // 防止DNS重绑定：将URL中的host替换为已验证的IP，用Host头传递原始域名
        $scheme = strtolower($parsedUrl['scheme']);
        $port = isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '';
        $path = $parsedUrl['path'] ?? '/';
        $query = isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '';
        $safeUrl = "{$scheme}://{$resolvedIP}{$port}{$path}{$query}";

        $postdata = [
            'title' => $this->order->title,
            'order_sn' => $this->order->order_sn,
            'email' => $this->order->email,
            'actual_price' => $this->order->actual_price,
            'order_info' => $this->order->info,
            'good_id' => $goodInfo->id,
            'gd_name' => $goodInfo->gd_name
        ];

        $headers = "Content-type: application/json\r\nHost: {$host}{$port}";
        $opts = [
            'http' => [
                'method'  => 'POST',
                'header'  => $headers,
                'content' => json_encode($postdata, JSON_UNESCAPED_UNICODE),
                'timeout' => 10,
            ],
            'ssl' => [
                'peer_name' => $host,
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ];
        $context = stream_context_create($opts);
        @file_get_contents($safeUrl, false, $context);
    }
}
