<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    // 顯示目前會員擁有的網站
    public function index(Request $request): Response
    {
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
        return Inertia::render('Sites/Create');
    }

    // 儲存新網站
    public function store(Request $request): RedirectResponse
    {
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
}
