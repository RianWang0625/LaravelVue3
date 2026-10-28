
<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
});

function submit() {
    form.post('/login', {
        onFinish: () => {
            form.reset('password');
        },
    });
}
</script>

<template>
    <Head title="會員登入" />

    <div class="login-page">
        <div class="login-card">
            <h1>會員登入</h1>
            <p class="subtitle">歡迎回到 SaaS 電商平台</p>

            <form @submit.prevent="submit">
                <div class="form-group">
                    <label for="email">電子郵件</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        required
                    />
                    <p v-if="form.errors.email" class="error">
                        {{ form.errors.email }}
                    </p>
                </div>

                <div class="form-group">
                    <label for="password">密碼</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        required
                    />
                    <p v-if="form.errors.password" class="error">
                        {{ form.errors.password }}
                    </p>
                </div>

                <button type="submit" :disabled="form.processing">
                    {{ form.processing ? '登入中...' : '登入' }}
                </button>
            </form>

            <p class="footer-text">
                還沒有帳號？
                <Link href="/register">立即註冊</Link>
            </p>

            <Link href="/" class="home-link">返回首頁</Link>
        </div>
    </div>
</template>

<style scoped>
.login-page {
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    background: #f3f4f6;
    padding: 24px;
    font-family: Arial, sans-serif;
}

.login-card {
    width: 100%;
    max-width: 440px;
    padding: 36px;
    border-radius: 14px;
    background: white;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
}

h1 {
    text-align: center;
    margin-bottom: 8px;
    font-size: 26px;
}

.subtitle {
    text-align: center;
    color: #6b7280;
    margin-bottom: 28px;
}

.form-group {
    margin-bottom: 18px;
}

label {
    display: block;
    margin-bottom: 7px;
    font-weight: 600;
}

input {
    width: 100%;
    padding: 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    box-sizing: border-box;
    font-size: 15px;
}

input:focus {
    outline: none;
    border-color: #2563eb;
}

.error {
    color: #dc2626;
    font-size: 13px;
    margin-top: 6px;
}

button {
    width: 100%;
    padding: 13px;
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 16px;
    font-weight: 600;
}

button:hover:not(:disabled) {
    background: #1d4ed8;
}

button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.footer-text {
    text-align: center;
    margin-top: 22px;
    color: #6b7280;
}

.footer-text a,
.home-link {
    color: #2563eb;
    text-decoration: none;
}

.home-link {
    display: block;
    text-align: center;
    margin-top: 16px;
}
</style>
