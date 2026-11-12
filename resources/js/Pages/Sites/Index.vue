<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Site {
    id: number;
    name: string;
    slug: string;
    site_type: string;
    status: string;
    domain: string | null;
}

// 接收網站資料
defineProps<{
    sites: Site[];
}>();

// 正在發布的商店 ID
const publishingSiteId = ref<number | null>(null);

// 發布商店
function publishSite(site: Site) {
    if (site.status !== 'draft') {
        return;
    }

    const confirmed = window.confirm(
        `確定要發布「${site.name}」嗎？發布後買家就能瀏覽並購買商品。`
    );

    if (!confirmed) {
        return;
    }

    publishingSiteId.value = site.id;

    router.patch(
        `/sites/${site.id}/publish`,
        {},
        {
            preserveScroll: true,

            onError: (errors) => {
                alert(
                    Object.values(errors).join('\n') ||
                    '發布失敗，請稍後再試。'
                );
            },

            onFinish: () => {
                publishingSiteId.value = null;
            },
        }
    );
}
</script>

<template>
    <Head title="我的網站" />

    <main style="max-width: 900px; margin: 40px auto; padding: 20px;">
        <h1>我的網站</h1>

        <Link href="/sites/create">
            + 建立網站
        </Link>

        <table style="width:100%; margin-top:25px; border-collapse:collapse;">
            <thead>
                <tr>
                    <th>網站名稱</th>
                    <th>網址代碼</th>
                    <th>網站類型</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>

            <tbody>
                <tr v-for="site in sites" :key="site.id">
                    <td>{{ site.name }}</td>
                    <td>{{ site.slug }}</td>
                    <td>{{ site.site_type }}</td>

                    <td>
                        <span v-if="site.status === 'published'">
                            已發布
                        </span>
                        <span v-else>
                            {{ site.status }}
                        </span>
                    </td>

                    <td>
                        <button
                            v-if="site.status === 'draft'"
                            type="button"
                            :disabled="publishingSiteId !== null"
                            @click="publishSite(site)"
                        >
                            {{
                                publishingSiteId === site.id
                                    ? '發布中...'
                                    : '發布商店'
                            }}
                        </button>

                        <span v-else-if="site.status === 'published'">
                            已發布
                        </span>
                    </td>
                </tr>

                <tr v-if="sites.length === 0">
                    <td colspan="5">目前沒有網站</td>
                </tr>
            </tbody>
        </table>
    </main>
</template>
