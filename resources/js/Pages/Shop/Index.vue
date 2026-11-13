<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';

// 載入預設商店模板
import DefaultHome from '../../ShopTemplates/Default/Home.vue';

// 商品規格資料
interface Variant {
    id: number;
    price: string | number;
    stock: number;
    is_active: boolean | number;
}

// 商品資料
interface Product {
    id: number;
    name: string;
    description: string | null;
    image: string | null;
    price: string | number;
    stock: number;
    variants: Variant[];
}

// 商店資料
interface Site {
    id: number;
    name: string;
    template_key?: string;
    design_mode?: string;
    theme?: {
        primary_color?: string;
        background_color?: string;
        logo?: string | null;
        banner?: string | null;
    };
}

// 接收 Laravel ShopController 傳入的資料
const props = defineProps<{
    site: Site;
    products: Product[];
}>();

// 模板白名單，未註冊的模板一律使用預設版型
const templateRegistry = {
    default: DefaultHome,
};

// 根據資料庫的 template_key 選擇模板
const selectedTemplate = computed(() => {
    const templateKey = props.site.template_key ?? 'default';

    if (Object.prototype.hasOwnProperty.call(templateRegistry, templateKey)) {
        return templateRegistry[
            templateKey as keyof typeof templateRegistry
        ];
    }

    return DefaultHome;
});
</script>

<template>
    <!-- 網站標題統一由 Inertia 頁面管理 -->
    <Head :title="site.name" />

    <!-- 根據商店設定載入指定模板 -->
    <component
        :is="selectedTemplate"
        :site="site"
        :products="products"
    />
</template>
