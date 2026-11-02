<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

// 建立網站表單
const form = useForm({
    name: '',
    slug: '',
    site_type: 'ecommerce',
});

function submit() {
    form.post('/sites');
}
</script>

<template>
    <Head title="建立網站" />

    <main style="max-width: 700px; margin: 40px auto; padding: 20px;">
        <h1>建立網站</h1>

        <form @submit.prevent="submit">

            <div style="margin-bottom: 20px;">
                <label>網站名稱</label>
                <input
                    v-model="form.name"
                    type="text"
                    placeholder="例如：日安購物商城"
                    required
                    style="display:block; width:100%; padding:10px;"
                />
                <div>{{ form.errors.name }}</div>
            </div>

            <div style="margin-bottom: 20px;">
                <label>網站網址代碼（Slug）</label>
                <input
                    v-model="form.slug"
                    type="text"
                    placeholder="例如：rian-store"
                    required
                    style="display:block; width:100%; padding:10px;"
                />
                <div>{{ form.errors.slug }}</div>
            </div>

            <div style="margin-bottom: 20px;">
                <label>網站類型</label>
                <select
                    v-model="form.site_type"
                    style="display:block; width:100%; padding:10px;"
                >
                    <option value="company">企業網站</option>
                    <option value="ecommerce">購物網站</option>
                    <option value="booking">預約網站</option>
                    <option value="event">活動網站</option>
                </select>
                <div>{{ form.errors.site_type }}</div>
            </div>

            <button type="submit" :disabled="form.processing">
                {{ form.processing ? '建立中...' : '建立網站' }}
            </button>

            <Link href="/sites" style="margin-left: 15px;">
                返回網站列表
            </Link>

        </form>
    </main>
</template>
