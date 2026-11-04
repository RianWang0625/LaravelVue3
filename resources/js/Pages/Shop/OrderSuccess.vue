<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

interface OrderItem {
    product_name: string;
    variant_options: Record<string, string> | null;
    unit_price: string | number;
    quantity: number;
    subtotal: string | number;
}

interface Order {
    id: number;
    order_number: string;
    subtotal: string | number;
    shipping_fee: string | number;
    total: string | number;
    payment_status: string;
    order_status: string;
    items: OrderItem[];
}

interface Site {
    id: number;
    name: string;
}

const props = defineProps<{
    site: Site;
    order: Order;
}>();

const shopUrl = computed(() => `/shop/${props.site.id}`);

const money = (value: string | number) => {
    return `NT$ ${Number(value).toLocaleString('zh-TW')}`;
};

function optionsText(options: Record<string, string> | null): string {
    if (!options) return '';

    return Object.entries(options)
        .map(([key, value]) => `${key}：${value}`)
        .join(' / ');
}
</script>

<template>
    <Head title="訂單建立成功" />

    <div class="success-page">
        <main class="success-card">
            <div class="success-icon">✓</div>

            <h1>訂單建立成功！</h1>

            <p class="intro">
                感謝你的訂購，系統已建立訂單。
            </p>

            <div class="order-number">
                <span>訂單編號</span>
                <strong>{{ order.order_number }}</strong>
            </div>

            <div class="status">
                <span>訂單狀態：待處理</span>
                <span>付款狀態：尚未付款</span>
            </div>

            <h2>訂購商品</h2>

            <div
                v-for="(item, index) in order.items"
                :key="index"
                class="order-item"
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

            <div class="amount-row">
                <span>商品小計</span>
                <span>{{ money(order.subtotal) }}</span>
            </div>

            <div class="amount-row">
                <span>運費</span>
                <span>{{ money(order.shipping_fee) }}</span>
            </div>

            <div class="amount-row total">
                <strong>訂單總金額</strong>
                <strong>{{ money(order.total) }}</strong>
            </div>

            <p class="notice">
                此訂單目前尚未完成付款。
                後續會加入正式付款、通知與訂單查詢功能。
            </p>

            <Link :href="shopUrl" class="shop-button">
                返回商店首頁
            </Link>
        </main>
    </div>
</template>

<style scoped>
* {
    box-sizing: border-box;
}

.success-page {
    min-height: 100vh;
    background: #f6f7fb;
    padding: 55px 16px;
    color: #20252b;
    font-family: Arial, "Noto Sans TC", sans-serif;
}

.success-card {
    max-width: 720px;
    margin: 0 auto;
    padding: 42px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.04);
}

.success-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: #dcfce7;
    color: #15803d;
    font-size: 38px;
    font-weight: 800;
    margin: 0 auto 22px;
}

h1 {
    text-align: center;
    font-size: 30px;
    font-weight: 800;
    margin-bottom: 12px;
}

.intro {
    color: #6b7280;
    text-align: center;
    margin-bottom: 30px;
}

.order-number {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    background: #eff6ff;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    overflow-wrap: anywhere;
}

.order-number span {
    color: #6b7280;
    font-size: 14px;
}

.order-number strong {
    font-size: 20px;
    color: #1d4ed8;
}

.status {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 32px;
    color: #92400e;
    font-size: 14px;
}

.status span {
    padding: 8px 12px;
    border-radius: 7px;
    background: #fef3c7;
}

h2 {
    font-size: 20px;
    font-weight: 800;
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 15px;
}

.order-item {
    display: flex;
    justify-content: space-between;
    gap: 18px;
    padding: 18px 0;
    border-bottom: 1px solid #e5e7eb;
}

.order-item p {
    color: #6b7280;
    font-size: 13px;
    margin: 8px 0;
}

.amount-row {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    margin-top: 18px;
}

.total {
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
    font-size: 20px;
    color: #c2410c;
}

.notice {
    background: #f9fafb;
    padding: 16px;
    border-radius: 8px;
    color: #6b7280;
    font-size: 13px;
    line-height: 1.8;
    margin: 30px 0;
}

.shop-button {
    display: block;
    background: #244c8f;
    color: white;
    text-align: center;
    padding: 14px;
    border-radius: 9px;
    font-weight: 800;
    text-decoration: none;
}

@media (max-width: 600px) {
    .success-card {
        padding: 24px 18px;
    }

    h1 {
        font-size: 24px;
    }

    .order-number strong {
        font-size: 16px;
    }
}
</style>
