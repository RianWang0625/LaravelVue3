<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    // 顯示目前會員擁有的網站
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Site::class);

        $sites = Site::query()
            ->where('owner_user_id', $request->user()->id)
            ->latest()
            ->get([
                'id',
                'name',
                'slug',
                'site_type',
                'status',
                'domain',
            ]);

        return Inertia::render('Sites/Index', [
            'sites' => $sites,
        ]);
    }

    // 顯示新增網站頁面
    public function create(): Response
    {
        Gate::authorize('create', Site::class);

        return Inertia::render('Sites/Create');
    }

    // 儲存新網站
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Site::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],

            'slug' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('sites', 'slug'),
            ],

            'site_type' => [
                'required',
                Rule::in([
                    'company',
                    'ecommerce',
                    'booking',
                    'event',
                ]),
            ],
        ]);

        Site::create([
            'owner_user_id' => $request->user()->id,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'site_type' => $validated['site_type'],
            'status' => 'draft',
            'settings' => [],
        ]);

        return redirect()
            ->route('sites.index')
            ->with('success', '網站建立成功！');
    }

    // 將目前商家擁有的草稿網站正式發布
    public function publish(Request $request, Site $site): RedirectResponse
    {
        // 驗證目前會員是否有權修改這個網站
        Gate::authorize('update', $site);

        // 只允許草稿網站進行發布
        if ($site->status !== 'draft') {
            return back()->withErrors([
                'status' => '只有草稿網站可以進行發布。',
            ]);
        }

        // 更新網站狀態
        $site->update([
            'status' => 'published',
        ]);

        return redirect()
            ->route('sites.index')
            ->with('success', '網站已正式發布！');
    }

}
