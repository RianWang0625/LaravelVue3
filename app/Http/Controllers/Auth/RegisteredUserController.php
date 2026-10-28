<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    // 顯示註冊頁面
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    // 處理註冊資料
    public function store(Request $request): RedirectResponse
    {
        // 驗證使用者輸入
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // 建立使用者，密碼由 User 模型自動雜湊
        $user = User::create($validated);

        // 觸發註冊事件
        event(new Registered($user));

        // 註冊成功後自動登入
        Auth::login($user);

        // 更新 Session ID，降低 Session Fixation 風險
        $request->session()->regenerate();

        return redirect('/');
    }
}
