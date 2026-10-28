
<script setup lang="ts">
import { ref } from 'vue';

const title = 'Laravel + Vue 3 SaaS 電商平台';
const count = ref<number>(0);

function addCount(): void {
    count.value++;
}
</script>

<template>
    <main class="container">
        <h1>{{ title }}</h1>

        <p>Laravel 13 + Vue 3 + TypeScript + Inertia</p>

        <p>目前計數：{{ count }}</p>

        <button @click="addCount">
            點擊測試 Vue
        </button>
    </main>
</template>

<style scoped>
.container {
    max-width: 800px;
    margin: 80px auto;
    padding: 24px;
    text-align: center;
    font-family: Arial, sans-serif;
}

button {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    background: #2563eb;
    color: white;
    cursor: pointer;
}
</style>
