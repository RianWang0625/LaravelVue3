<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

interface Stats {
    sellers: number;
    sites: number;
    published: number;
    draft: number;
}

defineProps<{
    stats: Stats;
}>();
</script>

<template>
    <Head title="平台管理後台" />

    <main class="dashboard">
        <h1>平台管理後台</h1>
        <p>管理 SaaS 平台上的商家與網站。</p>

        <div class="stats-grid">
            <div class="stat-card">
                <h2>商家總數</h2>
                <strong>{{ stats.sellers }}</strong>
            </div>

            <div class="stat-card">
                <h2>網站總數</h2>
                <strong>{{ stats.sites }}</strong>
            </div>

            <div class="stat-card">
                <h2>已發布網站</h2>
                <strong>{{ stats.published }}</strong>
            </div>

            <div class="stat-card">
                <h2>草稿網站</h2>
                <strong>{{ stats.draft }}</strong>
            </div>
        </div>

        <div class="actions">
            <Link href="/sites/create">
                替商家建立網站
            </Link>
        </div>
    </main>
</template>

<style scoped>
.dashboard {
    max-width: 1100px;
    margin: 40px auto;
    padding: 24px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(
        auto-fit,
        minmax(200px, 1fr)
    );
    gap: 20px;
    margin-top: 30px;
}

.stat-card {
    padding: 24px;
    border: 1px solid #ddd;
    border-radius: 12px;
    background: #fff;
    color: #222;
}

.stat-card h2 {
    font-size: 16px;
    margin-bottom: 12px;
}

.stat-card strong {
    font-size: 32px;
}

.actions {
    margin-top: 30px;
}

.actions a {
    display: inline-block;
    padding: 12px 20px;
    background: #2563eb;
    color: white;
    border-radius: 8px;
    text-decoration: none;
}
</style>
