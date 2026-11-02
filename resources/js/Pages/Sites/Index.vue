<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

interface Site {
    id: number;
    name: string;
    slug: string;
    site_type: string;
    status: string;
    domain: string | null;
}

// 接收網站資料
defineProps<{
    sites: Site[];
}>();
</script>

<template>
    <Head title="我的網站" />

    <main style="max-width: 900px; margin: 40px auto; padding: 20px;">
        <h1>我的網站</h1>

        <Link href="/sites/create">
            + 建立網站
        </Link>

        <table style="width:100%; margin-top:25px; border-collapse:collapse;">
            <thead>
                <tr>
                    <th>網站名稱</th>
                    <th>網址代碼</th>
                    <th>網站類型</th>
                    <th>狀態</th>
                </tr>
            </thead>

            <tbody>
                <tr v-for="site in sites" :key="site.id">
                    <td>{{ site.name }}</td>
                    <td>{{ site.slug }}</td>
                    <td>{{ site.site_type }}</td>
                    <td>{{ site.status }}</td>
                </tr>

                <tr v-if="sites.length === 0">
                    <td colspan="4">目前沒有網站</td>
                </tr>
            </tbody>
        </table>
    </main>
</template>
