
<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'

type CartItem = {
    key: string
    product_id: number
    variant_id: number | null
    name: string
    options: Record<string, string>
    image_url: string | null
    price: number
    quantity: number
    stock: number
    subtotal: number
}

type Shop = {
    id: number
    name: string
    slug: string
}

const props = defineProps<{
    site: Shop
    items: CartItem[]
    total: number
    count: number
}>()

const page = usePage()
const processing = ref(false)

const baseUrl = computed(() => `/shop/${props.site.id}`)
const cartUrl = computed(() => `${baseUrl.value}/cart`)

const money = (value: number) =>
    new Intl.NumberFormat('zh-TW', {
        style: 'currency',
        currency: 'TWD',
        maximumFractionDigits: 0,
    }).format(value)

const optionsText = (options: Record<string, string>) =>
    Object.entries(options)
        .map(([key, value]) => `${key}：${value}`)
        .join(' / ')

// 修改購買數量
function updateQuantity(item: CartItem, quantity: number) {
    if (processing.value || quantity < 1 || quantity > item.stock) {
        return
    }

    processing.value = true

    router.put(
        `${cartUrl.value}/${encodeURIComponent(item.key)}`,
        { quantity },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false
            },
        },
    )
}

// 刪除購物車商品
function removeItem(item: CartItem) {
    if (processing.value) return

    processing.value = true

    router.delete(
        `${cartUrl.value}/${encodeURIComponent(item.key)}`,
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false
            },
        },
    )
}
</script>

<template>
    <div class="cart-page">
        <div class="cart-container">
            <header class="cart-header">
                <a :href="baseUrl" class="back-link">← 繼續購物</a>
                <h1>{{ site.name }}｜購物車</h1>
                <p>目前共有 {{ count }} 件商品</p>
            </header>

            <div
                v-if="Object.keys(page.props.errors || {}).length"
                class="error-box"
            >
                <p
                    v-for="(message, field) in page.props.errors"
                    :key="field"
                >
                    {{ message }}
                </p>
            </div>

            <div v-if="items.length === 0" class="empty-cart">
                <h2>購物車目前沒有商品</h2>
                <p>去逛逛喜歡的商品吧！</p>
                <a :href="baseUrl" class="primary-button">
                    前往商店
                </a>
            </div>

            <div v-else class="cart-layout">
                <section class="cart-items">
                    <article
                        v-for="item in items"
                        :key="item.key"
                        class="cart-item"
                    >
                        <div class="product-image">
                            <img
                                v-if="item.image_url"
                                :src="item.image_url"
                                :alt="item.name"
                            />
                            <span v-else>暫無圖片</span>
                        </div>

                        <div class="product-info">
                            <h2>{{ item.name }}</h2>

                            <p
                                v-if="Object.keys(item.options).length"
                                class="variant"
                            >
                                {{ optionsText(item.options) }}
                            </p>

                            <p class="price">{{ money(item.price) }}</p>
                            <p class="stock">可購買庫存：{{ item.stock }}</p>

                            <div class="quantity-row">
                                <div class="quantity-control">
                                    <button
                                        type="button"
                                        :disabled="processing || item.quantity <= 1"
                                        @click="updateQuantity(item, item.quantity - 1)"
                                    >
                                        −
                                    </button>

                                    <span>{{ item.quantity }}</span>

                                    <button
                                        type="button"
                                        :disabled="processing || item.quantity >= item.stock"
                                        @click="updateQuantity(item, item.quantity + 1)"
                                    >
                                        +
                                    </button>
                                </div>

                                <button
                                    type="button"
                                    class="remove-button"
                                    :disabled="processing"
                                    @click="removeItem(item)"
                                >
                                    移除商品
                                </button>
                            </div>
                        </div>

                        <div class="subtotal">
                            {{ money(item.subtotal) }}
                        </div>
                    </article>
                </section>

                <aside class="summary">
                    <h2>訂單摘要</h2>

                    <div class="summary-row">
                        <span>商品數量</span>
                        <span>{{ count }} 件</span>
                    </div>

                    <div class="summary-row">
                        <span>商品小計</span>
                        <span>{{ money(total) }}</span>
                    </div>

                    <div class="summary-row">
                        <span>運費</span>
                        <span>結帳時計算</span>
                    </div>

                    <div class="summary-row total-row">
                        <strong>商品合計</strong>
                        <strong>{{ money(total) }}</strong>
                    </div>

                    <p class="checkout-note">
                        下一階段將串接收件資料、配送方式及結帳。
                    </p>

                    <Link
                        :href="`/shop/${site.id}/checkout`"
                        class="checkout-button"
                    >
                        前往結帳
                    </Link>

                    <a :href="baseUrl" class="continue-link">
                        繼續選購商品
                    </a>
                </aside>
            </div>
        </div>
    </div>
</template>

<style scoped>
.cart-page {
    min-height: 100vh;
    background: #f6f7fb;
    color: #222;
    padding: 40px 16px;
}

.cart-container {
    max-width: 1150px;
    margin: 0 auto;
}

.cart-header {
    margin-bottom: 28px;
}

.cart-header h1 {
    font-size: 30px;
    font-weight: 700;
    margin: 15px 0 8px;
}

.cart-header p,
.stock,
.variant,
.checkout-note {
    color: #777;
}

.back-link,
.continue-link {
    color: #2563eb;
    text-decoration: none;
}

.cart-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 320px;
    gap: 24px;
    align-items: start;
}

.cart-items,
.summary,
.empty-cart {
    background: white;
    border-radius: 14px;
    padding: 24px;
    box-shadow: 0 3px 18px rgba(0, 0, 0, 0.04);
}

.cart-item {
    display: flex;
    gap: 18px;
    padding: 20px 0;
    border-bottom: 1px solid #eee;
}

.cart-item:last-child {
    border-bottom: none;
}

.product-image {
    width: 125px;
    height: 125px;
    border-radius: 10px;
    background: #f2f2f2;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    color: #999;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-info {
    flex: 1;
    min-width: 0;
}

.product-info h2 {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 8px;
}

.price {
    font-weight: 700;
    color: #e11d48;
    margin: 10px 0;
}

.stock {
    font-size: 13px;
}

.quantity-row {
    display: flex;
    gap: 16px;
    align-items: center;
    flex-wrap: wrap;
    margin-top: 16px;
}

.quantity-control {
    display: flex;
    align-items: center;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
}

.quantity-control button {
    width: 36px;
    height: 36px;
    background: #f8f8f8;
}

.quantity-control button:disabled {
    opacity: 0.35;
}

.quantity-control span {
    min-width: 42px;
    text-align: center;
}

.remove-button {
    color: #dc2626;
    font-size: 14px;
}

.subtotal {
    font-weight: 700;
    white-space: nowrap;
}

.summary h2 {
    font-size: 21px;
    font-weight: 700;
    margin-bottom: 22px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 16px;
}

.total-row {
    border-top: 1px solid #eee;
    padding-top: 18px;
    font-size: 19px;
}

.checkout-note {
    font-size: 13px;
    line-height: 1.7;
    margin: 20px 0 12px;
}

.checkout-button,
.primary-button {
    display: block;
    width: 100%;
    text-align: center;
    padding: 13px;
    border-radius: 9px;
    background: #2563eb;
    color: white;
    font-weight: 600;
    text-decoration: none;
}

.checkout-button:disabled {
    background: #94a3b8;
    cursor: not-allowed;
}

.continue-link {
    display: block;
    margin-top: 18px;
    text-align: center;
}

.empty-cart {
    text-align: center;
    padding: 65px 24px;
}

.empty-cart h2 {
    font-size: 23px;
    margin-bottom: 12px;
}

.empty-cart .primary-button {
    max-width: 220px;
    margin: 25px auto 0;
}

.error-box {
    margin-bottom: 20px;
    padding: 14px 18px;
    background: #fef2f2;
    color: #b91c1c;
    border-radius: 8px;
}

@media (max-width: 800px) {
    .cart-layout {
        grid-template-columns: 1fr;
    }

    .cart-header h1 {
        font-size: 24px;
    }
}

@media (max-width: 520px) {
    .cart-page {
        padding: 20px 12px;
    }

    .cart-items,
    .summary {
        padding: 16px;
    }

    .cart-item {
        flex-wrap: wrap;
        gap: 12px;
    }

    .product-image {
        width: 90px;
        height: 90px;
    }

    .product-info h2 {
        font-size: 16px;
    }

    .subtotal {
        width: 100%;
        text-align: right;
    }
}
</style>
