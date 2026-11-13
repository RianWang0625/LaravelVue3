<script setup lang="ts">
import { computed } from 'vue';
// 模板只負責商店畫面與商品連結
import { Link } from '@inertiajs/vue3';

interface Variant {
    id: number;
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

    // 商店使用的模板
    template_key?: string;

    // 標準模板或付費客製化
    design_mode?: string;

    // 商店外觀設定
    theme?: {
        primary_color?: string;
        background_color?: string;
        logo?: string | null;
        banner?: string | null;
    };
}


// 接收指定網站的商品
const props = defineProps<{
    site: Site;
    products: Product[];
}>();

const shopUrl = `/shop/${props.site.id}`;

// 商品圖片網址
function imageUrl(path: string | null): string {
    return path ? `/storage/${path}` : '';
}

// 商品售價顯示
function displayPrice(product: Product): string {
    if (!product.variants?.length) {
        return money(product.price);
    }

    const prices = product.variants.map(v => Number(v.price));
    const min = Math.min(...prices);
    const max = Math.max(...prices);

    return min === max
        ? money(min)
        : `${money(min)} ～ ${money(max)}`;
}

// 商品庫存計算
function availableStock(product: Product): number {
    if (!product.variants?.length) {
        return Number(product.stock);
    }

    return product.variants.reduce(
        (sum, variant) => sum + Number(variant.stock),
        0
    );
}

function money(value: string | number): string {
    return `NT$ ${Number(value).toLocaleString('zh-TW')}`;
}

// 商品搜尋
const search = defineModel<string>('search', {
    default: '',
});

const filteredProducts = computed(() => {
    const keyword = search.value.trim().toLowerCase();

    if (!keyword) return props.products;

    return props.products.filter(product =>
        product.name.toLowerCase().includes(keyword)
    );
});
</script>

<template>
    <div class="shop-page">
        <!-- 網站導覽列 -->
        <header class="header">
            <div class="header-inner">
                <Link :href="shopUrl" class="brand">
                    {{ site.name }}
                </Link>

                <nav>
                    <Link :href="shopUrl">全部商品</Link>
                </nav>
            </div>
        </header>

        <!-- 商店主視覺 -->
        <section class="hero">
            <div class="container">
                <span class="eyebrow">WELCOME TO OUR STORE</span>
                <h1>{{ site.name }}</h1>
                <p>探索精選好物，找到適合你的商品。</p>
                <a href="#products" class="hero-button">
                    瀏覽商品
                </a>
            </div>
        </section>

        <!-- 商品展示 -->
        <main id="products" class="container catalog">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">OUR PRODUCTS</span>
                    <h2>精選商品</h2>
                    <p>共 {{ filteredProducts.length }} 件商品</p>
                </div>

                <input
                    v-model="search"
                    class="search-input"
                    type="search"
                    placeholder="搜尋商品名稱..."
                />
            </div>

            <div
                v-if="filteredProducts.length === 0"
                class="empty"
            >
                目前沒有符合條件的商品。
            </div>

            <div v-else class="product-grid">
                <Link
                    v-for="product in filteredProducts"
                    :key="product.id"
                    :href="`${shopUrl}/products/${product.id}`"
                    class="product-card"
                >
                    <div class="image-area">
                        <img
                            v-if="product.image"
                            :src="imageUrl(product.image)"
                            :alt="product.name"
                            loading="lazy"
                        />

                        <span v-else class="no-image">
                            暫無商品圖片
                        </span>

                        <span
                            v-if="availableStock(product) === 0"
                            class="sold-out"
                        >
                            已售完
                        </span>
                    </div>

                    <div class="product-info">
                        <h3>{{ product.name }}</h3>
                        <p class="price">
                            {{ displayPrice(product) }}
                        </p>

                        <p class="stock">
                            {{
                                availableStock(product) > 0
                                    ? `庫存 ${availableStock(product)} 件`
                                    : '目前缺貨'
                            }}
                        </p>

                        <span class="detail-link">
                            查看商品詳情 →
                        </span>
                    </div>
                </Link>
            </div>
        </main>

        <!-- 網站頁尾 -->
        <footer>
            <div class="container">
                <strong>{{ site.name }}</strong>
                <p>感謝你的光臨</p>
            </div>
        </footer>
    </div>
</template>

<style scoped>
* {
    box-sizing: border-box;
}

.shop-page {
    font-family: Arial, "Noto Sans TC", sans-serif;
    color: #20252b;
    background: #fafbfc;
    min-height: 100vh;
}

.container,
.header-inner {
    max-width: 1200px;
    margin: 0 auto;
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
    color: #111827;
    text-decoration: none;
    font-weight: 800;
    font-size: 22px;
}

nav a {
    color: #4b5563;
    text-decoration: none;
    font-size: 14px;
}

.hero {
    background: linear-gradient(135deg, #111827, #34445e);
    color: white;
    padding: 85px 0;
}

.hero h1 {
    font-size: clamp(32px, 5vw, 52px);
    margin: 14px 0;
}

.hero p {
    color: #d1d5db;
    line-height: 1.7;
}

.eyebrow {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 2px;
    color: #728cbd;
}

.hero .eyebrow {
    color: #c2d5ff;
}

.hero-button {
    display: inline-block;
    background: white;
    color: #111827;
    padding: 13px 22px;
    border-radius: 7px;
    text-decoration: none;
    margin-top: 20px;
    font-weight: 700;
}

.catalog {
    padding-top: 65px;
    padding-bottom: 90px;
}

.section-heading {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 30px;
}

.section-heading h2 {
    font-size: 30px;
    margin: 10px 0;
}

.section-heading p {
    color: #6b7280;
}

.search-input {
    width: 270px;
    max-width: 100%;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 13px 15px;
    background: white;
}

.product-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 22px;
}

.product-card {
    display: block;
    overflow: hidden;
    background: white;
    border: 1px solid #edf0f2;
    border-radius: 12px;
    color: inherit;
    text-decoration: none;
    transition: transform 0.2s, box-shadow 0.2s;
}

.product-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px #00000012;
}

.image-area {
    position: relative;
    background: #f3f4f6;
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}

.image-area img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.no-image {
    color: #9ca3af;
}

.sold-out {
    position: absolute;
    top: 12px;
    left: 12px;
    color: white;
    background: #374151;
    font-size: 12px;
    padding: 7px 12px;
    border-radius: 5px;
}

.product-info {
    padding: 18px;
}

.product-info h3 {
    font-size: 15px;
    line-height: 1.6;
    min-height: 48px;
    margin: 0 0 10px;
}

.price {
    color: #c2410c;
    font-weight: 800;
    font-size: 17px;
}

.stock {
    font-size: 13px;
    color: #6b7280;
    margin-top: 10px;
}

.detail-link {
    display: block;
    color: #244c8f;
    font-weight: 700;
    font-size: 13px;
    margin-top: 18px;
}

.empty {
    background: white;
    padding: 60px 20px;
    border-radius: 12px;
    text-align: center;
    color: #6b7280;
}

footer {
    border-top: 1px solid #e5e7eb;
    background: white;
    padding: 36px 0;
}

footer p {
    color: #9ca3af;
    font-size: 13px;
}

@media (max-width: 1000px) {
    .product-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 700px) {
    .product-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .container,
    .header-inner {
        padding-left: 16px;
        padding-right: 16px;
    }

    .hero {
        padding: 58px 0;
    }

    .catalog {
        padding-top: 42px;
    }

    .section-heading {
        align-items: stretch;
        flex-direction: column;
    }

    .search-input {
        width: 100%;
    }

    .product-info {
        padding: 12px;
    }

    .product-info h3 {
        font-size: 13px;
        min-height: 60px;
    }

    .price {
        font-size: 14px;
    }
}

@media (max-width: 390px) {
    .product-grid {
        grid-template-columns: 1fr;
    }
}
</style>
