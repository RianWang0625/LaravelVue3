<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    public function index(Request $request): Response
    {
        // 只有平台 Admin 可以查看所有商店
        abort_unless($request->user()->isAdmin(), 403);
        
        $search = trim((string) $request->query('search', ''));
        // 查詢平台全部商店，包含擁有者資料
        $sites = Site::query()
            ->with('owner:id,name,email')

            // 依商店名稱、Slug 或 Seller 資料搜尋
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhereHas('owner', function ($ownerQuery) use ($search) {
                            $ownerQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })

            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Site $site) => [
                'id' => $site->id,
                'name' => $site->name,
                'slug' => $site->slug,
                'site_type' => $site->site_type,
                'status' => $site->status,
                'created_at' => $site->created_at,
                'owner' => $site->owner
                    ? [
                        'id' => $site->owner->id,
                        'name' => $site->owner->name,
                        'email' => $site->owner->email,
                    ]
                    : null,
            ]);

        return Inertia::render('Admin/Sites/Index', [
            'sites' => $sites,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }
}
