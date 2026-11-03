<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

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

const selectedVariantId = ref<number | null>(
    props.product.variants?.[0]?.id ?? null
);

const hasVariants = computed(
    () => (props.product.variants?.length ?? 0) > 0
);

// 目前選擇的商品規格
const selectedVariant = computed(() => {
    return props.product.variants?.find(
        variant => variant.id === selectedVariantId.value
    ) ?? null;
});

// 商品價格及庫存
const currentPrice = computed(() => {
    return selectedVariant.value?.price ?? props.product.price;
});

const currentStock = computed(() => {
    return Number(
        selectedVariant.value?.stock ?? props.product.stock
    );
});

const formattedPrice = computed(() => {
    return `NT$ ${Number(currentPrice.value).toLocaleString('zh-TW')}`;
});

const imageUrl = computed(() => {
    return props.product.image
        ? `/storage/${props.product.image}`
        : null;
});

const shopUrl = `/shop/${props.site.id}`;

// 商品規格文字
function variantLabel(variant: Variant): string {
    return Object.entries(variant.options)
        .map(([name, value]) => `${name}：${value}`)
        .join(' / ');
}
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

                <Link :href="shopUrl" class="back-link">
                    返回商品列表
                </Link>
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
                                v-for="variant in product.variants"
                                :key="variant.id"
                                type="button"
                                class="variant-button"
                                :class="{
                                    selected: selectedVariantId === variant.id
                                }"
                                :disabled="Number(variant.stock) === 0"
                                @click="selectedVariantId = variant.id"
                            >
                                {{ variantLabel(variant) }}

                                <small v-if="Number(variant.stock) === 0">
                                    （售完）
                                </small>
                            </button>
                        </div>

                        <!-- 所有規格售完提示 -->
                        <p
                            v-if="product.variants.length > 0 &&
                                product.variants.every(variant => Number(variant.stock) === 0)"
                            class="text-danger mt-3"
                        >
                            目前所有規格皆已售完
                        </p>

                        <p
                            v-if="selectedVariant?.sku"
                            class="sku"
                        >
                            商品編號：{{ selectedVariant.sku }}
                        </p>
                    </div>

                    <div v-else class="variant-section">
                        <h3>商品資訊</h3>
                        <p>此商品沒有其他規格選項。</p>
                    </div>

                    <div class="purchase-status">
                        <span v-if="currentStock > 0">
                            商品可供選購
                        </span>
                        <span v-else class="out-of-stock">
                            目前無庫存
                        </span>
                    </div>

                    <p class="notice">
                        購物車與結帳功能開發中，目前可瀏覽商品與選擇規格。
                    </p>

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

.out-of-stock {
    color: #b91c1c;
}

.notice {
    color: #6b7280;
    font-size: 13px;
    line-height: 1.7;
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
</style>
