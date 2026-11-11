<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        // 只有平台管理員可以進入
        abort_unless($request->user()->isAdmin(), 403);

        // 統計平台商家數量
        $sellerCount = User::query()
            ->where('role', 'seller')
            ->count();

        // 統計全部商店數量
        $siteCount = Site::query()->count();

        // 統計已發布商店數量
        $publishedCount = Site::query()
            ->where('status', 'published')
            ->count();

        // 統計草稿商店數量
        $draftCount = Site::query()
            ->where('status', 'draft')
            ->count();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'sellers' => $sellerCount,
                'sites' => $siteCount,
                'published' => $publishedCount,
                'draft' => $draftCount,
            ],
        ]);
    }
}
