
<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

interface Variant {
    id: number;
    sku: string | null;
    options: Record<string, string>;
    price: string | number;
    stock: number;
    is_active: boolean | number;
}

interface Product {
    id: number;
    name: string;
    description: string | null;
    image: string | null;
    price: string | number;
    stock: number;
    variants: Variant[];
}

interface Site {
    id: number;
    name: string;
}

// 接收商品詳細資料
const props = defineProps<{
    site: Site;
    product: Product;
}>();

const page = usePage();

// 商品規格及數量狀態
const availableVariants = computed(() => {
    return (props.product.variants ?? []).filter(
        variant => Boolean(variant.is_active)
    );
});

const firstAvailableVariant = availableVariants.value.find(
    variant => Number(variant.stock) > 0
);

const selectedVariantId = ref<number | null>(
    firstAvailableVariant?.id ?? null
);

const quantity = ref(1);
const processing = ref(false);

const errorMessage = ref('');

// 商品規格判斷
const hasVariants = computed(() => availableVariants.value.length > 0);

const selectedVariant = computed(() => {
    return availableVariants.value.find(
        variant => variant.id === selectedVariantId.value
    ) ?? null;
});

// 商品價格及庫存
const currentPrice = computed(() => {
    return selectedVariant.value?.price ?? props.product.price;
});

const currentStock = computed(() => {
    if (hasVariants.value) {
        return Number(selectedVariant.value?.stock ?? 0);
    }

    return Number(props.product.stock);
});

const formattedPrice = computed(() => {
    return `NT$ ${Number(currentPrice.value).toLocaleString('zh-TW')}`;
});

const formattedSubtotal = computed(() => {
    const subtotal = Number(currentPrice.value) * quantity.value;
    return `NT$ ${subtotal.toLocaleString('zh-TW')}`;
});

// 商品圖片網址
const imageUrl = computed(() => {
    const image = props.product.image;

    if (!image) return null;

    if (
        image.startsWith('http://') ||
        image.startsWith('https://') ||
        image.startsWith('/')
    ) {
        return image;
    }

    return `/storage/${image}`;
});

const shopUrl = `/shop/${props.site.id}`;
const cartUrl = `${shopUrl}/cart`;

// 商品規格文字
function variantLabel(variant: Variant): string {
    return Object.entries(variant.options ?? {})
        .map(([name, value]) => `${name}：${value}`)
        .join(' / ');
}

// 切換規格後重設購買數量
watch(selectedVariantId, () => {
    quantity.value = 1;
    errorMessage.value = '';
});

// 增加購買數量
function increaseQuantity() {
    if (processing.value) return;

    if (quantity.value < currentStock.value) {
        quantity.value++;
    }
}

// 減少購買數量
function decreaseQuantity() {
    if (processing.value) return;

    if (quantity.value > 1) {
        quantity.value--;
    }
}

// 加入購物車
function addToCart() {
    errorMessage.value = '';

    if (processing.value) return;

    if (currentStock.value <= 0) {
        errorMessage.value = '這項商品目前沒有庫存。';
        return;
    }

    if (hasVariants.value && selectedVariantId.value === null) {
        errorMessage.value = '請先選擇商品規格。';
        return;
    }

    if (
        !Number.isInteger(quantity.value) ||
        quantity.value < 1 ||
        quantity.value > currentStock.value
    ) {
        errorMessage.value = '購買數量不正確或超過可購買庫存。';
        return;
    }

    processing.value = true;

    router.post(
        cartUrl,
        {
            product_id: props.product.id,
            variant_id: hasVariants.value
                ? selectedVariantId.value
                : null,
            quantity: quantity.value,
        },
        {
            preserveScroll: true,

            onError: (errors) => {
                errorMessage.value =
                    errors.quantity ||
                    errors.variant_id ||
                    errors.product_id ||
                    '加入購物車失敗，請稍後再試。';
            },

            onFinish: () => {
                processing.value = false;
            },
        }
    );
}

// 是否可以加入購物車
const canAddToCart = computed(() => {
    return (
        currentStock.value > 0 &&
        (!hasVariants.value || selectedVariantId.value !== null) &&
        !processing.value
    );
});
</script>

<template>
    <Head :title="product.name" />

    <div class="shop-page">
        <!-- 網站導覽列 -->
        <header class="header">
            <div class="header-inner">
                <Link :href="shopUrl" class="brand">
                    {{ site.name }}
                </Link>

                <div class="header-actions">
                    <Link :href="shopUrl" class="back-link">
                        返回商品列表
                    </Link>

                    <Link :href="cartUrl" class="cart-link">
                        🛒 購物車
                    </Link>
                </div>
            </div>
        </header>

        <main class="container">
            <div class="breadcrumb">
                <Link :href="shopUrl">全部商品</Link>
                <span> / {{ product.name }}</span>
            </div>

            <div class="product-layout">
                <!-- 商品圖片 -->
                <div class="image-box">
                    <img
                        v-if="imageUrl"
                        :src="imageUrl"
                        :alt="product.name"
                    />

                    <span v-else>暫無商品圖片</span>
                </div>

                <!-- 商品資訊 -->
                <div class="product-details">
                    <span class="eyebrow">PRODUCT DETAILS</span>

                    <h1>{{ product.name }}</h1>

                    <div class="price">
                        {{ formattedPrice }}
                    </div>

                    <p class="stock">
                        {{
                            currentStock > 0
                                ? `目前庫存：${currentStock} 件`
                                : '此規格目前已售完'
                        }}
                    </p>

                    <!-- 商品規格選擇 -->
                    <div v-if="hasVariants" class="variant-section">
                        <h3>選擇商品規格</h3>

                        <div class="variant-list">
                            <button
                                v-for="variant in availableVariants"
                                :key="variant.id"
                                type="button"
                                class="variant-button"
                                :class="{
                                    selected: selectedVariantId === variant.id
                                }"
                                :disabled="Number(variant.stock) <= 0 || processing"
                                @click="selectedVariantId = variant.id"
                            >
                                {{ variantLabel(variant) }}

                                <small v-if="Number(variant.stock) <= 0">
                                    （售完）
                                </small>
                            </button>
                        </div>

                        <p
                            v-if="availableVariants.every(
                                variant => Number(variant.stock) <= 0
                            )"
                            class="sold-out-message"
                        >
                            目前所有規格皆已售完
                        </p>

                        <p v-if="selectedVariant?.sku" class="sku">
                            商品編號：{{ selectedVariant.sku }}
                        </p>
                    </div>

                    <div v-else class="variant-section">
                        <h3>商品資訊</h3>
                        <p>此商品沒有其他規格選項。</p>
                    </div>

                    <!-- 商品庫存狀態 -->
                    <div
                        class="purchase-status"
                        :class="{ unavailable: currentStock <= 0 }"
                    >
                        <span v-if="currentStock > 0">
                            商品可供選購
                        </span>

                        <span v-else>
                            目前無庫存
                        </span>
                    </div>

                    <!-- 商品數量選擇 -->
                    <div class="quantity-section">
                        <h3>購買數量</h3>

                        <div class="quantity-control">
                            <button
                                type="button"
                                :disabled="quantity <= 1 || processing"
                                @click="decreaseQuantity"
                            >
                                −
                            </button>

                            <span class="quantity-value">
                                {{ quantity }}
                            </span>

                            <button
                                type="button"
                                :disabled="
                                    currentStock <= 0 ||
                                    quantity >= currentStock ||
                                    processing
                                "
                                @click="increaseQuantity"
                            >
                                +
                            </button>
                        </div>

                        <p class="quantity-hint">
                            最多可購買 {{ currentStock }} 件
                        </p>
                    </div>

                    <!-- 購物金額 -->
                    <div class="purchase-total">
                        <span>商品小計</span>
                        <strong>{{ formattedSubtotal }}</strong>
                    </div>

                    <!-- 購物車錯誤訊息 -->
                    <p
                        v-if="errorMessage"
                        class="error-message"
                        role="alert"
                    >
                        {{ errorMessage }}
                    </p>

                    <p
                        v-if="Object.keys(page.props.errors || {}).length && !errorMessage"
                        class="error-message"
                        role="alert"
                    >
                        請確認商品規格及購買數量後重試。
                    </p>

                    <!-- 加入購物車 -->
                    <div class="purchase-actions">
                        <button
                            type="button"
                            class="add-cart-button"
                            :disabled="!canAddToCart"
                            @click="addToCart"
                        >
                            {{
                                processing
                                    ? '加入中...'
                                    : currentStock <= 0
                                        ? '商品已售完'
                                        : '🛒 加入購物車'
                            }}
                        </button>

                        <Link :href="cartUrl" class="view-cart-button">
                            查看購物車
                        </Link>
                    </div>

                    <p class="notice">
                        加入購物車後可調整數量及移除商品。
                        結帳與付款功能將於下一階段開放。
                    </p>

                    <!-- 商品介紹 -->
                    <div class="description">
                        <h3>商品介紹</h3>

                        <p>
                            {{ product.description || '目前尚無商品描述。' }}
                        </p>
                    </div>
                </div>
            </div>
        </main>

        <footer>
            <div class="container">
                {{ site.name }} · 感謝你的光臨
            </div>
        </footer>
    </div>
</template>

<style scoped>
* {
    box-sizing: border-box;
}

.shop-page {
    min-height: 100vh;
    background: #fafbfc;
    color: #20252b;
    font-family: Arial, "Noto Sans TC", sans-serif;
}

.container,
.header-inner {
    max-width: 1200px;
    margin: auto;
    padding-left: 24px;
    padding-right: 24px;
}

.header {
    background: white;
    border-bottom: 1px solid #e5e7eb;
}

.header-inner {
    min-height: 76px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 22px;
}

.brand {
    font-size: 22px;
    font-weight: 800;
    text-decoration: none;
    color: #111827;
}

.back-link,
.breadcrumb a {
    color: #244c8f;
    text-decoration: none;
}

.cart-link {
    background: #244c8f;
    padding: 11px 18px;
    border-radius: 8px;
    color: white;
    text-decoration: none;
    font-weight: 700;
    font-size: 14px;
}

.breadcrumb {
    margin: 30px 0;
    color: #6b7280;
    font-size: 14px;
}

.product-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 55px;
    padding-bottom: 80px;
}

.image-box {
    background: #f3f4f6;
    border-radius: 16px;
    display: flex;
    justify-content: center;
    align-items: center;
    aspect-ratio: 1;
    overflow: hidden;
    color: #9ca3af;
}

.image-box img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.product-details {
    padding-top: 15px;
}

.eyebrow {
    letter-spacing: 2px;
    font-size: 12px;
    font-weight: 700;
    color: #728cbd;
}

h1 {
    font-size: clamp(26px, 3vw, 36px);
    line-height: 1.4;
    margin: 15px 0 25px;
}

.price {
    font-size: 30px;
    font-weight: 800;
    color: #c2410c;
}

.stock {
    color: #4b5563;
    margin: 16px 0 28px;
}

.variant-section {
    padding: 25px 0;
    border-top: 1px solid #e5e7eb;
}

h3 {
    font-size: 17px;
}

.variant-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 18px;
}

.variant-button {
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 12px 16px;
    cursor: pointer;
    font-size: 14px;
}

.variant-button.selected {
    border: 2px solid #244c8f;
    background: #eff6ff;
    color: #1d4ed8;
    font-weight: 700;
}

.variant-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.sold-out-message {
    color: #b91c1c;
    margin-top: 15px;
    font-size: 14px;
}

.sku {
    font-size: 13px;
    color: #6b7280;
    margin-top: 18px;
}

.purchase-status {
    background: #eaf5ee;
    color: #166534;
    padding: 15px;
    border-radius: 8px;
    font-weight: 700;
}

.purchase-status.unavailable {
    background: #fef2f2;
    color: #b91c1c;
}

.quantity-section {
    padding: 25px 0;
}

.quantity-control {
    display: inline-flex;
    align-items: center;
    border: 1px solid #d1d5db;
    border-radius: 9px;
    overflow: hidden;
    margin-top: 12px;
}

.quantity-control button {
    width: 45px;
    height: 45px;
    border: none;
    background: #f3f4f6;
    cursor: pointer;
    font-size: 22px;
}

.quantity-control button:disabled {
    cursor: not-allowed;
    opacity: 0.35;
}

.quantity-value {
    display: flex;
    justify-content: center;
    align-items: center;
    min-width: 65px;
    height: 45px;
    background: white;
    font-weight: 700;
}

.quantity-hint {
    color: #6b7280;
    font-size: 13px;
    margin-top: 12px;
}

.purchase-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    border-top: 1px solid #e5e7eb;
    padding: 25px 0;
}

.purchase-total strong {
    font-size: 25px;
    color: #c2410c;
}

.purchase-actions {
    display: flex;
    gap: 12px;
}

.add-cart-button,
.view-cart-button {
    min-height: 50px;
    padding: 14px 18px;
    border-radius: 9px;
    font-size: 15px;
    font-weight: 700;
    text-align: center;
}

.add-cart-button {
    flex: 2;
    background: #244c8f;
    color: white;
    border: none;
    cursor: pointer;
}

.add-cart-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.view-cart-button {
    flex: 1;
    background: white;
    color: #244c8f;
    border: 1px solid #244c8f;
    text-decoration: none;
}

.error-message {
    background: #fef2f2;
    color: #b91c1c;
    border-radius: 8px;
    padding: 12px 15px;
    font-size: 14px;
    margin-bottom: 14px;
}

.notice {
    color: #6b7280;
    font-size: 13px;
    line-height: 1.7;
    margin-top: 16px;
}

.description {
    border-top: 1px solid #e5e7eb;
    margin-top: 35px;
    padding-top: 22px;
}

.description p {
    white-space: pre-line;
    color: #4b5563;
    line-height: 1.9;
}

footer {
    border-top: 1px solid #e5e7eb;
    background: white;
    padding: 30px 0;
    color: #6b7280;
    font-size: 13px;
}

@media (max-width: 768px) {
    .container,
    .header-inner {
        padding-left: 16px;
        padding-right: 16px;
    }

    .product-layout {
        grid-template-columns: 1fr;
        gap: 25px;
    }

    .breadcrumb {
        margin: 20px 0;
    }

    .product-details {
        padding-top: 0;
    }

    .price {
        font-size: 25px;
    }
}

@media (max-width: 520px) {
    .header-inner {
        min-height: 65px;
    }

    .header-actions {
        gap: 10px;
    }

    .brand {
        font-size: 17px;
    }

    .back-link {
        display: none;
    }

    .cart-link {
        padding: 9px 12px;
    }

    .purchase-actions {
        flex-direction: column;
    }

    .add-cart-button,
    .view-cart-button {
        width: 100%;
    }
}
</style>
