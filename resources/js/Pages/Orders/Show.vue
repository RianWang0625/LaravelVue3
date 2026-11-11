
<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface OrderItem {
    id: number;
    product_name: string;
    variant_options: Record<string, string> | null;
    unit_price: string | number;
    quantity: number;
    subtotal: string | number;
}

interface Order {
    id: number;
    order_number: string;
    site: { id: number; name: string };
    customer_name: string;
    customer_phone: string;
    customer_email: string;
    recipient_name: string;
    recipient_phone: string;
    shipping_address: string | null;
    shipping_method: string;
    subtotal: string | number;
    shipping_fee: string | number;
    discount_amount: string | number;
    total: string | number;
    payment_method: string;
    payment_status: string;
    order_status: string;
    customer_note: string | null;
    shipping_company: string | null;
    tracking_number: string | null;
    shipped_at: string | null;
    created_at: string;
    items: OrderItem[];
}

const props = defineProps<{
    order: Order;
}>();

// 訂單管理表單狀態
const page = usePage();
const shippingCompany = ref('');
const trackingNumber = ref('');
const processing = ref(false);
const orderUrl = computed(
    () => `/seller/orders/${props.order.id}`
);

// 本機測試付款功能
const isLocalEnvironment = import.meta.env.DEV;
function simulatePayment() {
    if (!isLocalEnvironment || processing.value) return;
    if (!window.confirm('確定要模擬付款成功嗎？此操作僅供本機測試。')) {
        return;
    }
    processing.value = true;
    router.patch(`${orderUrl.value}/simulate-payment`, {}, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
        },
    });
}



const orderErrors = computed(
    () => page.props.errors as Record<string, string>
);

// 更新訂單處理狀態
function processOrder() {
    if (processing.value) return;

    processing.value = true;

    router.patch(`${orderUrl.value}/process`, {}, {
        preserveScroll: true,
        onFinish: () => processing.value = false,
    });
}

// 登記出貨
function shipOrder() {
    if (processing.value) return;

    processing.value = true;

    router.patch(`${orderUrl.value}/ship`, {
        shipping_company: shippingCompany.value,
        tracking_number: trackingNumber.value,
    }, {
        preserveScroll: true,
        onFinish: () => processing.value = false,
    });
}

// 完成訂單
function completeOrder() {
    if (processing.value) return;

    processing.value = true;

    router.patch(`${orderUrl.value}/complete`, {}, {
        preserveScroll: true,
        onFinish: () => processing.value = false,
    });
}

// 取消訂單並恢復庫存
function cancelOrder() {
    if (processing.value) return;

    if (!window.confirm('確定取消這筆訂單並恢復庫存嗎？')) {
        return;
    }

    processing.value = true;

    router.patch(`${orderUrl.value}/cancel`, {}, {
        preserveScroll: true,
        onFinish: () => processing.value = false,
    });
}
const money = (value: string | number) =>
    `NT$ ${Number(value).toLocaleString('zh-TW')}`;

function optionsText(options: Record<string, string> | null): string {
    return Object.entries(options ?? {})
        .map(([key, value]) => `${key}：${value}`)
        .join(' / ');
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
</script>

<template>
    <Head :title="`訂單 ${order.order_number}`" />

    <main class="page">
        <div class="container">
            <header class="header">
                <Link href="/seller/orders" class="back">
                    ← 返回訂單列表
                </Link>
                <h1>訂單明細</h1>
                <p>{{ order.order_number }}</p>
            </header>

            <div class="layout">
                <div class="main-column">
                    <!-- 訂購商品 -->
                    <section class="panel">
                        <h2>訂購商品</h2>

                        <div
                            v-for="item in order.items"
                            :key="item.id"
                            class="item"
                        >
                            <div>
                                <strong>{{ item.product_name }}</strong>
                                <p v-if="item.variant_options">
                                    {{ optionsText(item.variant_options) }}
                                </p>
                                <p>
                                    {{ money(item.unit_price) }}
                                    × {{ item.quantity }}
                                </p>
                            </div>
                            <strong>{{ money(item.subtotal) }}</strong>
                        </div>
                    </section>

                    <!-- 訂購人資料 -->
                    <section class="panel">
                        <h2>訂購人資料</h2>
                        <p>姓名：{{ order.customer_name }}</p>
                        <p>電話：{{ order.customer_phone }}</p>
                        <p>Email：{{ order.customer_email }}</p>
                    </section>

                    <!-- 收件與配送 -->
                    <section class="panel">
                        <h2>收件及配送資料</h2>
                        <p>收件人：{{ order.recipient_name }}</p>
                        <p>電話：{{ order.recipient_phone }}</p>
                        <p>地址：{{ order.shipping_address || '未填寫' }}</p>
                        <p>配送方式：{{ order.shipping_method }}</p>
                        <p>物流公司：{{ order.shipping_company || '尚未出貨' }}</p>
                        <p>物流單號：{{ order.tracking_number || '尚未出貨' }}</p>
                        <p v-if="order.customer_note">
                            客戶備註：{{ order.customer_note }}
                        </p>
                    </section>
                </div>

                <aside class="side-column">
                    <!-- 訂單狀態 -->
                    <section class="panel">
                        <h2>訂單資訊</h2>
                        <p>商店：{{ order.site.name }}</p>
                        <p>訂單狀態：{{ statusText(order.order_status) }}</p>
                        <p>付款狀態：{{ paymentText(order.payment_status) }}</p>
                        <p>
                            建立時間：
                            {{ new Date(order.created_at).toLocaleString('zh-TW') }}
                        </p>
                    </section>

                    <!-- 訂單金額 -->
                    <section class="panel">
                        <h2>金額明細</h2>

                        <div class="amount-row">
                            <span>商品小計</span>
                            <span>{{ money(order.subtotal) }}</span>
                        </div>

                        <div class="amount-row">
                            <span>運費</span>
                            <span>{{ money(order.shipping_fee) }}</span>
                        </div>

                        <div class="amount-row">
                            <span>折扣</span>
                            <span>− {{ money(order.discount_amount) }}</span>
                        </div>

                        <div class="amount-row total">
                            <strong>訂單總額</strong>
                            <strong>{{ money(order.total) }}</strong>
                        </div>
                    </section>

                    <!-- 商家訂單操作 -->
                    <section class="panel order-actions">
                        <h2>訂單管理</h2>
                        <!-- 本機模擬付款 -->
                        <div
                            v-if="
                                isLocalEnvironment &&
                                order.payment_status === 'pending' &&
                                ['pending', 'processing'].includes(order.order_status)
                            "
                            class="test-payment"
                        >
                            <p class="action-hint">
                                開發測試功能：不會實際扣款。
                            </p>

                            <button
                                type="button"
                                class="action-button test"
                                :disabled="processing"
                                @click="simulatePayment"
                            >
                                模擬付款成功
                            </button>
                        </div>

                        <p
                            v-for="(message, field) in orderErrors"
                            :key="field"
                            class="action-error"
                        >
                            {{ message }}
                        </p>

                        <button
                            v-if="order.order_status === 'pending'"
                            type="button"
                            class="action-button primary"
                            :disabled="processing"
                            @click="processOrder"
                        >
                            開始處理訂單
                        </button>

                        <template v-if="order.order_status === 'processing'">
                            <div v-if="order.payment_status === 'paid'">
                                <label class="action-label">物流公司</label>
                                <input
                                    v-model="shippingCompany"
                                    class="action-input"
                                    placeholder="例如：黑貓宅急便"
                                />

                                <label class="action-label">物流單號</label>
                                <input
                                    v-model="trackingNumber"
                                    class="action-input"
                                    placeholder="請輸入物流單號"
                                />

                                <button
                                    type="button"
                                    class="action-button primary"
                                    :disabled="processing"
                                    @click="shipOrder"
                                >
                                    確認出貨
                                </button>
                            </div>

                            <p v-else class="action-hint">
                                此訂單尚未付款，暫時不能登記出貨。
                            </p>
                        </template>

                        <button
                            v-if="
                                order.order_status === 'shipped' &&
                                order.payment_status === 'paid'
                            "
                            type="button"
                            class="action-button primary"
                            :disabled="processing"
                            @click="completeOrder"
                        >
                            標記訂單完成
                        </button>

                        <button
                            v-if="
                                ['pending', 'processing'].includes(order.order_status) &&
                                order.payment_status === 'pending'
                            "
                            type="button"
                            class="action-button danger"
                            :disabled="processing"
                            @click="cancelOrder"
                        >
                            取消訂單並恢復庫存
                        </button>

                        <p v-if="order.order_status === 'cancelled'" class="action-hint">
                            此訂單已取消，庫存已依取消流程處理。
                        </p>

                        <p v-if="order.order_status === 'completed'" class="action-hint">
                            此訂單已完成。
                        </p>
                    </section>
                </aside>
            </div>
        </div>
    </main>
</template>

<style scoped>
* {
    box-sizing: border-box;
}

.page {
    min-height: 100vh;
    padding: 35px 16px;
    background: #f6f7fb;
    color: #20252b;
    font-family: Arial, "Noto Sans TC", sans-serif;
}

.container {
    max-width: 1150px;
    margin: auto;
}

.header {
    margin-bottom: 28px;
}

.back {
    color: #244c8f;
    text-decoration: none;
    font-weight: 700;
}

.header h1 {
    font-size: 30px;
    font-weight: 800;
    margin: 16px 0 6px;
}

.header p {
    color: #64748b;
    overflow-wrap: anywhere;
}

.layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 320px;
    align-items: start;
    gap: 24px;
}

.main-column,
.side-column {
    display: grid;
    gap: 20px;
}

.panel {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 24px;
}

.panel h2 {
    font-size: 20px;
    font-weight: 800;
    margin: 0 0 20px;
}

.panel p {
    margin: 11px 0;
    line-height: 1.7;
    overflow-wrap: anywhere;
}

.item {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    padding: 18px 0;
    border-bottom: 1px solid #e5e7eb;
}

.item:last-child {
    border-bottom: none;
}

.item p {
    color: #64748b;
    font-size: 14px;
}

.item > strong {
    white-space: nowrap;
}

.amount-row {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    padding: 11px 0;
}

.amount-row.total {
    border-top: 1px solid #e5e7eb;
    margin-top: 8px;
    padding-top: 18px;
    font-size: 18px;
    color: #c2410c;
}

.notice {
    color: #64748b;
    font-size: 13px;
    line-height: 1.8;
}

@media (max-width: 820px) {
    .layout {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 520px) {
    .panel {
        padding: 18px;
    }

    .header h1 {
        font-size: 24px;
    }
}

/* 訂單操作按鈕及輸入欄位 */
.order-actions {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.action-button {
    width: 100%;
    padding: 12px 14px;
    border: none;
    border-radius: 8px;
    font-weight: 700;
    cursor: pointer;
}

.action-button.primary {
    background: #244c8f;
    color: white;
}

.action-button.danger {
    background: #fee2e2;
    color: #b91c1c;
}

.action-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.action-label {
    display: block;
    margin: 12px 0 6px;
    font-weight: 700;
    font-size: 13px;
}

.action-input {
    width: 100%;
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: white;
}

.action-error {
    padding: 10px;
    color: #b91c1c;
    background: #fef2f2;
    border-radius: 8px;
    font-size: 13px;
}

.action-hint {
    font-size: 13px;
    color: #64748b;
    line-height: 1.7;
}
</style>
