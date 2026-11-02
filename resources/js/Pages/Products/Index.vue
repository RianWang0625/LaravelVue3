
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

// 商品與網站資料型態
interface Product {
    id: number;
    name: string;
    description: string | null;
    price: number | string;
    stock: number;
    image: string | null;
    is_active: boolean;
}

interface Site {
    id: number;
    name: string;
}

// 接收 Laravel 傳來的資料
const props = defineProps<{
    products: Product[];
    sites: Site[];
}>();

// 商品新增表單
const form = useForm({
    site_id: props.sites[0]?.id ?? '',
    name: '',
    description: '',
    price: '' as string | number,
    stock: 0,
});

// 送出新增商品
function submit() {
    //Vue與Laravel的連接
    form.post('/products', {
        onSuccess: () => {
            form.reset('name', 'description', 'price', 'stock');
        },
    });
}
</script>

<template>
    <Head title="商品管理" />

    <main class="container py-5">
        <h1 class="mb-4">商品管理</h1>

        <h3 class="mb-3">新增商品</h3>

        <form @submit.prevent="submit" class="mb-5">

            <div class="mb-3">
                <label>所屬網站</label>
                <select v-model="form.site_id" class="form-control" required>
                    <option value="" disabled>請選擇網站</option>
                    <option
                        v-for="site in props.sites"
                        :key="site.id"
                        :value="site.id"
                    >
                        {{ site.name }}
                    </option>
                </select>
                <div v-if="form.errors.site_id" class="text-danger">
                    {{ form.errors.site_id }}
                </div>
            </div>

            <div class="mb-3">
                <label>商品名稱</label>
                <input v-model="form.name" type="text"
                    class="form-control" required>
                <div v-if="form.errors.name" class="text-danger">
                    {{ form.errors.name }}
                </div>
            </div>

            <div class="mb-3">
                <label>商品描述</label>
                <textarea v-model="form.description"
                    class="form-control"></textarea>
            </div>

            <div class="mb-3">
                <label>商品價格</label>
                <input v-model="form.price" type="number"
                    min="0" step="0.01" class="form-control" required>
                <div v-if="form.errors.price" class="text-danger">
                    {{ form.errors.price }}
                </div>
            </div>

            <div class="mb-3">
                <label>商品庫存</label>
                <input v-model="form.stock" type="number"
                    min="0" class="form-control" required>
                <div v-if="form.errors.stock" class="text-danger">
                    {{ form.errors.stock }}
                </div>
            </div>

            <div v-if="sites.length === 0" class="alert alert-warning">
                目前沒有網站，請先建立網站才能新增商品。
            </div>

            <button
                type="submit"
                class="btn btn-primary"
                :disabled="form.processing || sites.length === 0"
            >
                {{ form.processing ? '新增中...' : '新增商品' }}
            </button>

        </form>

        <hr>

        <h3 class="mb-3">商品列表</h3>

        <div v-if="products.length === 0">
            目前沒有商品
        </div>

        <table v-else class="table table-bordered">
            <thead>
                <tr>
                    <th>商品名稱</th>
                    <th>價格</th>
                    <th>庫存</th>
                    <th>狀態</th>
                </tr>
            </thead>

            <tbody>
                <tr v-for="product in products" :key="product.id">
                    <td>{{ product.name }}</td>
                    <td>NT$ {{ product.price }}</td>
                    <td>{{ product.stock }}</td>
                    <td>{{ product.is_active ? '上架' : '下架' }}</td>
                </tr>
            </tbody>
        </table>
    </main>
</template>
