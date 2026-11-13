<?php

namespace App\Http\Controllers;

use App\Models\User;
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

    public function create(Request $request): Response
    {
        // 確認使用者有建立商店的權限
        Gate::authorize('create', Site::class);

        $isAdmin = $request->user()->isAdmin();

        // 只有 Admin 可以取得 Seller 帳號清單
        $sellers = $isAdmin
            ? User::query()
                ->where('role', 'seller')
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
            : [];

        return Inertia::render('Sites/Create', [
            'isAdmin' => $isAdmin,
            'sellers' => $sellers,
        ]);
    }


    // 儲存新網站
    
    public function store(Request $request): RedirectResponse
    {
        // 確認使用者具有建立商店的權限
        Gate::authorize('create', Site::class);

        $user = $request->user();

        // 驗證商店基本資料
        $rules = [
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
        ];

        // 只有 Admin 需要指定商店擁有者
        if ($user->isAdmin()) {
            $rules['owner_user_id'] = [
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where('role', 'seller'),
            ];
        }

        $validated = $request->validate($rules);

        // Seller 自己建立；Admin 指定 Seller
        $ownerUserId = $user->isAdmin()
            ? $validated['owner_user_id']
            : $user->id;

        Site::create([
            'owner_user_id' => $ownerUserId,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'site_type' => $validated['site_type'],
            'status' => 'draft',

            // 設定網站預設模板與外觀
            'settings' => [
                'template_key' => 'default',
                'design_mode' => 'standard',
                'theme' => [
                    'primary_color' => '#2563EB',
                    'background_color' => '#FFFFFF',
                    'logo' => null,
                    'banner' => null,
                ],
            ],
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
