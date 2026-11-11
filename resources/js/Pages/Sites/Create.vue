<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

// Seller 帳號資料
interface Seller {
    id: number;
    name: string;
    email: string;
}

// 接收 Laravel Controller 傳入的資料
const props = defineProps<{
    isAdmin: boolean;
    sellers: Seller[];
}>();

// 建立網站表單
const form = useForm({
    name: '',
    slug: '',
    site_type: 'ecommerce',
    owner_user_id: '',
});

function submit() {
    // Seller 不需要提交擁有者欄位
    form.transform((data) => {
        if (props.isAdmin) {
            return data;
        }

        const { owner_user_id, ...sellerData } = data;
        return sellerData;
    }).post('/sites');
}
</script>

<template>
    <Head title="建立網站" />

    <main style="max-width: 700px; margin: 40px auto; padding: 20px;">
        <h1>建立網站</h1>

        <form @submit.prevent="submit">

            <!-- Admin 才顯示商店擁有者選單 -->
            <div v-if="isAdmin" style="margin-bottom: 20px;">
                <label for="owner_user_id">商店擁有者（Seller）</label>

                <select
                    id="owner_user_id"
                    v-model="form.owner_user_id"
                    required
                    style="display:block; width:100%; padding:10px;"
                >
                    <option value="" disabled>請選擇商家</option>

                    <option
                        v-for="seller in sellers"
                        :key="seller.id"
                        :value="String(seller.id)"
                    >
                        {{ seller.name }}（{{ seller.email }}）
                    </option>
                </select>

                <div v-if="form.errors.owner_user_id" style="color: red;">
                    {{ form.errors.owner_user_id }}
                </div>

                <p v-if="sellers.length === 0" style="color: red;">
                    目前沒有 Seller 帳號，請先建立商家帳號。
                </p>
            </div>

            <!-- 網站名稱 -->
            <div style="margin-bottom: 20px;">
                <label for="site_name">網站名稱</label>

                <input
                    id="site_name"
                    v-model="form.name"
                    type="text"
                    placeholder="例如：日安購物商城"
                    required
                    style="display:block; width:100%; padding:10px;"
                />

                <div>{{ form.errors.name }}</div>
            </div>

            <!-- 網站 Slug -->
            <div style="margin-bottom: 20px;">
                <label for="site_slug">網站網址代碼（Slug）</label>

                <input
                    id="site_slug"
                    v-model="form.slug"
                    type="text"
                    placeholder="例如：rian-store"
                    required
                    style="display:block; width:100%; padding:10px;"
                />

                <div>{{ form.errors.slug }}</div>
            </div>

            <!-- 網站類型 -->
            <div style="margin-bottom: 20px;">
                <label for="site_type">網站類型</label>

                <select
                    id="site_type"
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

            <!-- 建立網站 -->
            <button
                type="submit"
                :disabled="form.processing || (isAdmin && sellers.length === 0)"
            >
                {{ form.processing ? '建立中...' : '建立網站' }}
            </button>

            <Link href="/sites" style="margin-left: 15px;">
                返回網站列表
            </Link>

        </form>
    </main>
</template>
