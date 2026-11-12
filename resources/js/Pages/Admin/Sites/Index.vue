<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

// 商店擁有者
interface Owner {
    id: number;
    name: string;
    email: string;
}

// 商店資料
interface Site {
    id: number;
    name: string;
    slug: string;
    site_type: string;
    status: string;
    created_at: string;
    owner: Owner | null;
}

// Laravel 分頁連結
interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

// 接收 Laravel 傳入的分頁資料

const props = defineProps<{
    sites: {
        data: Site[];
        links: PaginationLink[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: {
        search: string;
    };
}>();

// 搜尋關鍵字
const search = ref(props.filters.search ?? '');

// 送出搜尋
function submitSearch() {
    router.get(
        '/admin/sites',
        { search: search.value.trim() },
        {
            preserveState: true,
            replace: true,
        }
    );
}

// 清除搜尋條件
function clearSearch() {
    search.value = '';

    router.get('/admin/sites', {}, {
        preserveState: true,
        replace: true,
    });
}


function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('zh-TW');
}

function statusLabel(status: string): string {
    return status === 'published' ? '已發布' : '草稿';
}
</script>

<template>
    <Head title="平台商店管理" />

    <main class="page">
        <div class="header">
            <div>
                <h1>平台商店管理</h1>
                <p>查看所有 Seller 的商店與發布狀態。</p>
            </div>

            <Link href="/admin">返回管理後台</Link>
        </div>

        <div class="summary">
            平台商店總數：{{ sites.total }}
        </div>

        <!-- Admin 商店搜尋 -->
        <form class="search-form" @submit.prevent="submitSearch">
            <input
                v-model="search"
                type="text"
                placeholder="搜尋商店名稱、Slug、Seller 名稱或 Email"
                aria-label="搜尋商店"
            />

            <button type="submit">
                搜尋
            </button>

            <button
                type="button"
                @click="clearSearch"
            >
                清除
            </button>
        </form>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>商店名稱</th>
                        <th>Slug</th>
                        <th>所屬 Seller</th>
                        <th>狀態</th>
                        <th>建立時間</th>
                    </tr>
                </thead>

                <tbody>
                    <tr
                        v-for="site in sites.data"
                        :key="site.id"
                    >
                        <td>{{ site.name }}</td>

                        <td>{{ site.slug }}</td>

                        <td>
                            <template v-if="site.owner">
                                {{ site.owner.name }}
                                <small>{{ site.owner.email }}</small>
                            </template>
                            <span v-else>無擁有者</span>
                        </td>

                        <td>
                            <span
                                class="status"
                                :class="site.status"
                            >
                                {{ statusLabel(site.status) }}
                            </span>
                        </td>

                        <td>{{ formatDate(site.created_at) }}</td>
                    </tr>

                    <tr v-if="sites.data.length === 0">
                        <td colspan="5" class="empty">
                            目前沒有商店資料
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- 分頁 -->
        <nav
            v-if="sites.last_page > 1"
            class="pagination"
            aria-label="商店列表分頁"
        >
            <template
                v-for="(link, index) in sites.links"
                :key="index"
            >
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="page-link"
                    :class="{ active: link.active }"
                    v-html="link.label"
                />

                <span
                    v-else
                    class="page-link disabled"
                    v-html="link.label"
                />
            </template>
        </nav>
    </main>
</template>

<style scoped>
.page {
    max-width: 1200px;
    margin: 40px auto;
    padding: 24px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
}

.header h1 {
    font-size: 28px;
    font-weight: bold;
}

.summary {
    margin: 24px 0;
    font-weight: bold;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 14px;
    border-bottom: 1px solid #ddd;
    text-align: left;
}

th {
    background: #f3f4f6;
    color: #222;
}

td small {
    display: block;
    color: #777;
}

.status {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 6px;
    background: #f3f4f6;
    color: #333;
}

.status.published {
    background: #dcfce7;
    color: #166534;
}

.status.draft {
    background: #fef3c7;
    color: #92400e;
}

.empty {
    text-align: center;
    padding: 30px;
}

.pagination {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 24px;
}

.page-link {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    text-decoration: none;
}

.page-link.active {
    background: #2563eb;
    color: white;
}

.page-link.disabled {
    opacity: 0.4;
}

.search-form {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 24px;
}

.search-form input {
    flex: 1;
    min-width: 240px;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 6px;
}

.search-form button {
    padding: 10px 18px;
    border: 1px solid #ddd;
    border-radius: 6px;
    cursor: pointer;
}

.search-form button[type="submit"] {
    background: #2563eb;
    color: white;
    border-color: #2563eb;
}

</style>
