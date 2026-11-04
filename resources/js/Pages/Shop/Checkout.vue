<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

interface OrderItem {
    product_id: number;
    product_variant_id: number | null;
    product_name: string;
    variant_options: Record<string, string> | null;
    unit_price: string | number;
    quantity: number;
    subtotal: string | number;
}

interface Site {
    id: number;
    name: string;
}

const props = defineProps<{
    site: Site;
    items: OrderItem[];
    subtotal: number;
    shippingFee: number;
    total: number;
    checkoutToken: string;
}>();

const shopUrl = computed(() => `/shop/${props.site.id}`);
const cartUrl = computed(() => `${shopUrl.value}/cart`);
const checkoutUrl = computed(() => `${shopUrl.value}/checkout`);

const sameRecipient = ref(true);

const form = useForm({
    checkout_token: props.checkoutToken,
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    recipient_name: '',
    recipient_phone: '',
    shipping_address: '',
    customer_note: '',
});

// 取得後端購物車驗證錯誤
const cartError = computed(() => {
    return (form.errors as Record<string, string | undefined>).cart;
});

const money = (value: string | number) => {
    return `NT$ ${Number(value).toLocaleString('zh-TW')}`;
};

// 將訂購人資訊同步至收件人
function syncRecipient() {
    if (sameRecipient.value) {
        form.recipient_name = form.customer_name;
        form.recipient_phone = form.customer_phone;
    }
}

// 顯示商品規格
function optionsText(options: Record<string, string> | null): string {
    if (!options) return '';

    return Object.entries(options)
        .map(([key, value]) => `${key}：${value}`)
        .join(' / ');
}

// 送出訂單
function submitOrder() {
    syncRecipient();

    form.post(checkoutUrl.value, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="訂單結帳" />

    <div class="checkout-page">
        <div class="container">
            <header class="page-header">
                <Link :href="cartUrl" class="back-link">
                    ← 返回購物車
                </Link>

                <h1>{{ site.name }}｜訂單結帳</h1>
                <p>請確認訂購資料與收件資訊</p>
            </header>

            <form class="checkout-layout" @submit.prevent="submitOrder">
                <div class="form-column">
                    <!-- 訂購人資料 -->
                    <section class="panel">
                        <h2>1. 訂購人資料</h2>

                        <div class="form-grid">
                            <div class="field">
                                <label for="customer-name">訂購人姓名 *</label>
                                <input
                                    id="customer-name"
                                    v-model="form.customer_name"
                                    type="text"
                                    maxlength="100"
                                    required
                                    @input="syncRecipient"
                                />
                                <small v-if="form.errors.customer_name" class="error">
                                    {{ form.errors.customer_name }}
                                </small>
                            </div>

                            <div class="field">
                                <label for="customer-phone">聯絡電話 *</label>
                                <input
                                    id="customer-phone"
                                    v-model="form.customer_phone"
                                    type="tel"
                                    maxlength="30"
                                    required
                                    @input="syncRecipient"
                                />
                                <small v-if="form.errors.customer_phone" class="error">
                                    {{ form.errors.customer_phone }}
                                </small>
                            </div>

                            <div class="field full">
                                <label for="customer-email">Email *</label>
                                <input
                                    id="customer-email"
                                    v-model="form.customer_email"
                                    type="email"
                                    maxlength="150"
                                    required
                                />
                                <small v-if="form.errors.customer_email" class="error">
                                    {{ form.errors.customer_email }}
                                </small>
                            </div>
                        </div>
                    </section>

                    <!-- 收件人資料 -->
                    <section class="panel">
                        <h2>2. 收件人資料</h2>

                        <label class="checkbox-row">
                            <input
                                v-model="sameRecipient"
                                type="checkbox"
                                @change="syncRecipient"
                            />
                            收件人與訂購人相同
                        </label>

                        <div class="form-grid">
                            <div class="field">
                                <label for="recipient-name">收件人姓名 *</label>
                                <input
                                    id="recipient-name"
                                    v-model="form.recipient_name"
                                    type="text"
                                    maxlength="100"
                                    :readonly="sameRecipient"
                                    required
                                />
                                <small v-if="form.errors.recipient_name" class="error">
                                    {{ form.errors.recipient_name }}
                                </small>
                            </div>

                            <div class="field">
                                <label for="recipient-phone">收件人電話 *</label>
                                <input
                                    id="recipient-phone"
                                    v-model="form.recipient_phone"
                                    type="tel"
                                    maxlength="30"
                                    :readonly="sameRecipient"
                                    required
                                />
                                <small v-if="form.errors.recipient_phone" class="error">
                                    {{ form.errors.recipient_phone }}
                                </small>
                            </div>

                            <div class="field full">
                                <label for="shipping-address">配送地址 *</label>
                                <textarea
                                    id="shipping-address"
                                    v-model="form.shipping_address"
                                    rows="3"
                                    maxlength="1000"
                                    placeholder="請填寫完整收件地址"
                                    required
                                />
                                <small v-if="form.errors.shipping_address" class="error">
                                    {{ form.errors.shipping_address }}
                                </small>
                            </div>
                        </div>
                    </section>

                    <!-- 配送與付款 -->
                    <section class="panel">
                        <h2>3. 配送與付款方式</h2>

                        <div class="option-box">
                            <strong>宅配到府</strong>
                            <span>{{ money(shippingFee) }}</span>
                        </div>

                        <p class="hint">
                            目前先使用固定運費作為測試設定。
                        </p>

                        <div class="option-box payment-box">
                            <strong>付款狀態：尚未付款</strong>
                            <span>待後續金流串接</span>
                        </div>

                        <p class="hint">
                            送出後會建立待付款訂單，不會進行線上扣款。
                        </p>
                    </section>

                    <!-- 訂單備註 -->
                    <section class="panel">
                        <h2>4. 訂單備註</h2>

                        <textarea
                            v-model="form.customer_note"
                            rows="4"
                            maxlength="2000"
                            placeholder="如有特殊配送需求，可以填寫在這裡"
                        />

                        <small v-if="form.errors.customer_note" class="error">
                            {{ form.errors.customer_note }}
                        </small>
                    </section>
                </div>

                <!-- 訂單摘要 -->
                <aside class="summary panel">
                    <h2>訂單摘要</h2>

                    <div
                        v-for="(item, index) in items"
                        :key="index"
                        class="summary-item"
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

                    <div class="summary-row">
                        <span>商品小計</span>
                        <span>{{ money(subtotal) }}</span>
                    </div>

                    <div class="summary-row">
                        <span>運費</span>
                        <span>{{ money(shippingFee) }}</span>
                    </div>

                    <div class="summary-row total">
                        <strong>訂單總金額</strong>
                        <strong>{{ money(total) }}</strong>
                    </div>

                    <p v-if="cartError" class="error-box">
                        {{ cartError }}
                    </p>

                    <button
                        type="submit"
                        class="submit-button"
                        :disabled="form.processing"
                    >
                        {{
                            form.processing
                                ? '訂單建立中...'
                                : '確認送出訂單'
                        }}
                    </button>

                    <p class="notice">
                        送出訂單前請再次確認資料。
                        此階段只建立待付款訂單，不會收取款項。
                    </p>
                </aside>
            </form>
        </div>
    </div>
</template>

<style scoped>
* {
    box-sizing: border-box;
}

.checkout-page {
    min-height: 100vh;
    background: #f6f7fb;
    color: #20252b;
    padding: 35px 16px 70px;
    font-family: Arial, "Noto Sans TC", sans-serif;
}

.container {
    max-width: 1150px;
    margin: 0 auto;
}

.page-header {
    margin-bottom: 28px;
}

.back-link {
    color: #244c8f;
    text-decoration: none;
}

.page-header h1 {
    font-size: 29px;
    font-weight: 800;
    margin: 18px 0 8px;
}

.page-header p,
.hint,
.notice {
    color: #6b7280;
}

.checkout-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 340px;
    gap: 24px;
    align-items: start;
}

.form-column {
    display: grid;
    gap: 20px;
}

.panel {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 25px;
}

.panel h2 {
    font-size: 20px;
    font-weight: 800;
    margin: 0 0 24px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.field.full {
    grid-column: 1 / -1;
}

.field label {
    font-size: 14px;
    font-weight: 700;
}

input:not([type="checkbox"]),
textarea {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    background: white;
    color: #20252b;
    font-size: 15px;
}

input:focus,
textarea:focus {
    outline: 2px solid #93c5fd;
    border-color: #2563eb;
}

input[readonly] {
    background: #f3f4f6;
}

.checkbox-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 22px;
    cursor: pointer;
}

.option-box {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    padding: 16px;
    border: 1px solid #93c5fd;
    background: #eff6ff;
    border-radius: 9px;
}

.payment-box {
    margin-top: 24px;
    border-color: #e5e7eb;
    background: #f9fafb;
}

.hint,
.notice {
    font-size: 13px;
    line-height: 1.8;
    margin-top: 10px;
}

.summary {
    position: sticky;
    top: 20px;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    padding: 15px 0;
    border-bottom: 1px solid #e5e7eb;
}

.summary-item p {
    font-size: 13px;
    color: #6b7280;
    margin: 7px 0;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    margin: 17px 0;
}

.summary-row.total {
    border-top: 1px solid #e5e7eb;
    padding-top: 20px;
    font-size: 19px;
    color: #c2410c;
}

.submit-button {
    width: 100%;
    border: none;
    border-radius: 9px;
    background: #244c8f;
    color: white;
    padding: 15px;
    font-size: 16px;
    font-weight: 800;
    cursor: pointer;
    margin-top: 15px;
}

.submit-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.error {
    color: #b91c1c;
    font-size: 13px;
}

.error-box {
    padding: 12px;
    background: #fef2f2;
    color: #b91c1c;
    border-radius: 8px;
}

@media (max-width: 850px) {
    .checkout-layout {
        grid-template-columns: 1fr;
    }

    .summary {
        position: static;
    }
}

@media (max-width: 550px) {
    .form-grid {
        grid-template-columns: 1fr;
    }

    .panel {
        padding: 18px;
    }

    .page-header h1 {
        font-size: 23px;
    }

    .option-box {
        flex-direction: column;
    }
}
</style>
