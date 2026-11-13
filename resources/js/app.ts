import { createApp, h } from 'vue';
import type { DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

// 定義 Vite 載入的 Vue 頁面模組型別
type PageModule = {
    default: DefineComponent;
};

// 預先載入所有 Inertia Vue 頁面
const pages = import.meta.glob<PageModule>('./Pages/**/*.vue', {
    eager: true,
});

// 建立 Inertia 應用程式
createInertiaApp({
    // 根據 Laravel 傳來的頁面名稱取得 Vue 元件
    resolve: (name) => {
        const page = pages[`./Pages/${name}.vue`];

        // 找不到頁面時顯示錯誤
        if (!page) {
            throw new Error(`找不到 Vue 頁面：${name}`);
        }

        return page.default;
    },

    // 初始化 Vue 並掛載 Inertia
    setup({ el, App, props, plugin }) {
        createApp({
            render: () => h(App, props),
        })
            .use(plugin)
            .mount(el);
    },
});
