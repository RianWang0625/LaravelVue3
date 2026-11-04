
<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';

interface Site {
    id: number;
    name: string;
    slug: string;
}

interface Order {
    id: number;
    site_id: number;
    site: { id: number; name: string } | null;
    order_number: string;
    customer_name: string;
    customer_phone: string;
    total: string | number;
    payment_status: string;
    order_status: string;
    items_count: number;
    created_at: string;
}

interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface OrdersPage {
    data: Order[];
    links: PageLink[];
    total: number;
    current_page: number;
    last_page: number;
}

const props = defineProps<{
    sites: Site[];
    orders: OrdersPage;
    filters: {
        search: string;
        site_id: string | number;
    };
}>();

const search = ref(props.filters.search ?? '');
const selectedSite = ref(String(props.filters.site_id ?? ''));

const money = (value: string | number) =>
    `NT$ ${Number(value).toLocaleString('zh-TW')}`;

const hasOrders = computed(() => props.orders.data.length > 0);

// 搜尋商家所屬網站的訂單
function applyFilters() {
    router.get(
        '/seller/orders',
        {
            search: search.value,
            site_id: selectedSite.value,
        },
        {
            preserveState: true,
            replace: true,
        }
    );
}

// 清除搜尋條件
function clearFilters() {
    search.value = '';
    selectedSite.value = '';
    applyFilters();
}

function statusText(status: string): string {
    const labels: Record<string, string> = {
        pending: '待處理',
        processing: '處理中',
        shipped: '已出貨',
        completed: '已完成',
        cancelled: '已取消',
    };

    return labels[status] ?? status;
}

function paymentText(status: string): string {
    const labels: Record<string, string> = {
        pending: '待付款',
        paid: '已付款',
        failed: '付款失敗',
        refunded: '已退款',
    };

    return labels[status] ?? status;
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString('zh-TW');
}
</script>

<template>
    <Head title="訂單管理" />

    <main class="orders-page">
        <div class="container">
            <header class="page-header">
                <div>
                    <p class="eyebrow">SELLER MANAGEMENT</p>
                    <h1>訂單管理</h1>
                    <p>查看與管理所屬商店的客戶訂單</p>
                </div>

                <Link href="/products" class="back-link">
                    返回商品管理
                </Link>
            </header>

            <!-- 訂單搜尋與網站篩選 -->
            <section class="filter-panel">
                <form class="filter-row" @submit.prevent="applyFilters">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="搜尋訂單編號、姓名或電話"
                    />

                    <select v-model="selectedSite">
                        <option value="">全部網站</option>
                        <option
                            v-for="site in sites"
                            :key="site.id"
                            :value="String(site.id)"
                        >
                            {{ site.name }}
                        </option>
                    </select>

                    <button type="submit" class="search-button">
                        搜尋
                    </button>

                    <button
                        type="button"
                        class="clear-button"
                        @click="clearFilters"
                    >
                        清除
                    </button>
                </form>
            </section>

            <!-- 訂單列表 -->
            <section class="table-panel">
                <div class="table-heading">
                    <h2>訂單列表</h2>
                    <span>共 {{ orders.total }} 筆訂單</span>
                </div>

                <div v-if="hasOrders" class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>訂單編號</th>
                                <th>商店</th>
                                <th>訂購人</th>
                                <th>金額</th>
                                <th>付款狀態</th>
                                <th>訂單狀態</th>
                                <th>成立時間</th>
                                <th>操作</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="order in orders.data" :key="order.id">
                                <td class="order-number">
                                    {{ order.order_number }}
                                </td>

                                <td>{{ order.site?.name ?? '未知商店' }}</td>

                                <td>
                                    <strong>{{ order.customer_name }}</strong>
                                    <small>{{ order.customer_phone }}</small>
                                </td>

                                <td class="amount">{{ money(order.total) }}</td>

                                <td>{{ paymentText(order.payment_status) }}</td>

                                <td>
                                    <span class="status">
                                        {{ statusText(order.order_status) }}
                                    </span>
                                </td>

                                <td>{{ formatDate(order.created_at) }}</td>

                                <td>
                                    <Link
                                        :href="`/seller/orders/${order.id}`"
                                        class="detail-link"
                                    >
                                        查看明細
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="empty-state">
                    <h3>目前沒有符合條件的訂單</h3>
                    <p>有新訂單成立後，就會顯示在這裡。</p>
                </div>

                <!-- 訂單分頁 -->
                <nav v-if="orders.last_page > 1" class="pagination">
                    <template v-for="(link, index) in orders.links" :key="index">
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
            </section>
        </div>
    </main>
</template>

<style scoped>
* {
    box-sizing: border-box;
}

.orders-page {
    min-height: 100vh;
    padding: 36px 16px;
    background: #f6f7fb;
    color: #20252b;
    font-family: Arial, "Noto Sans TC", sans-serif;
}

.container {
    max-width: 1280px;
    margin: 0 auto;
}

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 25px;
}

.eyebrow {
    font-size: 12px;
    color: #64748b;
    font-weight: 700;
    letter-spacing: 1px;
}

h1 {
    margin: 6px 0;
    font-size: 30px;
    font-weight: 800;
}

.page-header p,
.table-heading span,
.empty-state p {
    color: #64748b;
}

.back-link {
    color: #244c8f;
    text-decoration: none;
    font-weight: 700;
}

.filter-panel,
.table-panel {
    padding: 24px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    margin-bottom: 20px;
}

.filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.filter-row input {
    flex: 1;
    min-width: 200px;
}

.filter-row input,
.filter-row select {
    min-height: 43px;
    padding: 10px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: white;
}

.filter-row button {
    min-height: 43px;
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
}

.search-button {
    background: #244c8f;
    color: white;
}

.clear-button {
    background: #e2e8f0;
    color: #334155;
}

.table-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.table-heading h2 {
    font-size: 20px;
    font-weight: 800;
    margin: 0;
}

.table-scroll {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

th,
td {
    text-align: left;
    padding: 15px 12px;
    border-bottom: 1px solid #e5e7eb;
    white-space: nowrap;
}

th {
    color: #64748b;
    background: #f8fafc;
}

.order-number {
    font-weight: 700;
    color: #244c8f;
}

td small {
    display: block;
    margin-top: 5px;
    color: #64748b;
}

.amount {
    font-weight: 800;
}

.status {
    padding: 6px 10px;
    background: #eff6ff;
    color: #1d4ed8;
    border-radius: 6px;
}

.detail-link {
    color: #244c8f;
    font-weight: 700;
    text-decoration: none;
}

.empty-state {
    text-align: center;
    padding: 60px 15px;
}

.empty-state h3 {
    font-size: 18px;
}

.pagination {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    justify-content: center;
    margin-top: 24px;
}

.page-link {
    min-width: 36px;
    padding: 8px 12px;
    text-align: center;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    color: #334155;
    text-decoration: none;
}

.page-link.active {
    background: #244c8f;
    border-color: #244c8f;
    color: white;
}

.page-link.disabled {
    opacity: 0.45;
}

@media (max-width: 600px) {
    .filter-panel,
    .table-panel {
        padding: 16px;
    }

    .filter-row input,
    .filter-row select,
    .filter-row button {
        width: 100%;
    }

    h1 {
        font-size: 24px;
    }
}
</style>
