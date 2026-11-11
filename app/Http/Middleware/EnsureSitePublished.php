<?php

namespace App\Http\Middleware;

use App\Models\Site;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSitePublished
{
    // 確認商店已正式發布，才能進行購物車與結帳操作
    public function handle(Request $request, Closure $next): Response
    {
        // 取得網址中的商店參數
        $site = $request->route('site');

        // 若路由模型尚未綁定，先依照 ID 查詢商店
        if (!$site instanceof Site) {
            $site = Site::findOrFail($site);
        }

        // 只有正式發布的商店可以進行購物
        abort_unless($site->status === 'published', 404);

        // 驗證通過，繼續執行原本的請求
        return $next($request);
    }
}
