<script setup lang="ts">
import { ref, onBeforeUnmount } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';

interface ProductVariant {
    id: number;
    product_id: number;
    sku: string | null;
    options: Record<string, string>;
    price: string | number;
    stock: number;
    is_active: boolean | number;
}

interface Product {
    id: number;
    site_id: number;
    name: string;
    description: string | null;
    price: string | number;
    stock: number;
    image: string | null;
    is_active: boolean | number;
    variants?: ProductVariant[];
}

interface Site {
    id: number;
    name: string;
}

interface OptionRow {
    name: string;
    value: string;
}

// 接收 Laravel 資料
const props = defineProps<{
    products: Product[];
    sites: Site[];
}>();

// 商品圖片設定
const newImagePreview = ref<string | null>(null);
const editImagePreview = ref<string | null>(null);
const imageInput = ref<HTMLInputElement | null>(null);
const editImageInput = ref<HTMLInputElement | null>(null);
const imageError = ref('');
const editImageError = ref('');

const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
const maxImageSize = 5 * 1024 * 1024;

function imageUrl(path: string | null): string | null {
    return path ? '/storage/' + path : null;
}

function clearPreview(url: string | null) {
    if (url?.startsWith('blob:')) {
        URL.revokeObjectURL(url);
    }
}

function validateImage(file: File): string {
    if (!allowedTypes.includes(file.type)) {
        return '只能上傳 JPG、PNG 或 WebP 圖片';
    }

    if (file.size > maxImageSize) {
        return '圖片不能超過 5MB';
    }

    return '';
}

// 新增商品
const form = useForm({
    site_id: props.sites[0]?.id ?? '',
    name: '',
    description: '',
    price: '' as string | number,
    stock: 0,
    image: null as File | null,
});

function onImageChange(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) return;

    imageError.value = validateImage(file);

    if (imageError.value) {
        input.value = '';
        return;
    }

    clearPreview(newImagePreview.value);

    form.image = file;
    newImagePreview.value = URL.createObjectURL(file);
    form.clearErrors('image');
}

function submit() {
    if (imageError.value) return;

    form.post('/products', {
        forceFormData: true,
        onSuccess: () => {
            form.reset('name', 'description', 'price', 'stock', 'image');

            clearPreview(newImagePreview.value);
            newImagePreview.value = null;

            imageError.value = '';

            if (imageInput.value) {
                imageInput.value.value = '';
            }
        },
    });
}

// 編輯商品
const editingId = ref<number | null>(null);

const editForm = useForm({
    _method: 'PUT',
    name: '',
    description: '',
    price: 0 as string | number,
    stock: 0,
    is_active: true,
    image: null as File | null,
});

function startEdit(product: Product) {
    clearPreview(editImagePreview.value);

    editingId.value = product.id;

    editForm.name = product.name;
    editForm.description = product.description ?? '';
    editForm.price = product.price;
    editForm.stock = product.stock;
    editForm.is_active = Boolean(product.is_active);
    editForm.image = null;

    editImagePreview.value = imageUrl(product.image);
    editImageError.value = '';

    editForm.clearErrors();
}

function onEditImageChange(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) return;

    editImageError.value = validateImage(file);

    if (editImageError.value) {
        input.value = '';
        return;
    }

    clearPreview(editImagePreview.value);

    editForm.image = file;
    editImagePreview.value = URL.createObjectURL(file);
    editForm.clearErrors('image');
}

function cancelEdit() {
    clearPreview(editImagePreview.value);

    editImagePreview.value = null;
    editImageError.value = '';
    editingId.value = null;

    editForm.reset();
}

function saveEdit(productId: number) {
    if (editImageError.value) return;

    editForm.post(`/products/${productId}`, {
        forceFormData: true,
        onSuccess: () => {
            cancelEdit();
        },
    });
}

// 商品上下架
function toggleActive(product: Product) {
    router.put(`/products/${product.id}`, {
        name: product.name,
        description: product.description,
        price: product.price,
        stock: product.stock,
        is_active: !Boolean(product.is_active),
    });
}

// 刪除商品
function deleteProduct(product: Product) {
    if (!confirm(`確定刪除「${product.name}」嗎？`)) {
        return;
    }

    router.delete(`/products/${product.id}`);
}

// 商品規格管理
const managingProductId = ref<number | null>(null);
const editingVariantId = ref<number | null>(null);
const variantMessage = ref('');

const optionRows = ref<OptionRow[]>([
    { name: '顏色', value: '' },
]);

const variantForm = useForm({
    sku: '',
    price: 0 as string | number,
    stock: 0,
    is_active: true,
    options: {} as Record<string, string>,
});

function resetVariantForm() {
    editingVariantId.value = null;
    variantMessage.value = '';

    variantForm.reset();
    variantForm.clearErrors();

    optionRows.value = [
        { name: '顏色', value: '' },
    ];
}

function toggleVariantManager(product: Product) {
    if (managingProductId.value === product.id) {
        managingProductId.value = null;
        resetVariantForm();
        return;
    }

    managingProductId.value = product.id;
    resetVariantForm();
}

function addOptionRow() {
    optionRows.value.push({
        name: '',
        value: '',
    });
}

function removeOptionRow(index: number) {
    if (optionRows.value.length <= 1) return;

    optionRows.value.splice(index, 1);
}

function startVariantEdit(variant: ProductVariant) {
    editingVariantId.value = variant.id;
    variantMessage.value = '';

    variantForm.sku = variant.sku ?? '';
    variantForm.price = variant.price;
    variantForm.stock = variant.stock;
    variantForm.is_active = Boolean(variant.is_active);

    optionRows.value = Object.entries(variant.options).map(
        ([name, value]) => ({
            name,
            value,
        })
    );

    variantForm.clearErrors();
}

function buildOptions(): Record<string, string> | null {
    const options: Record<string, string> = Object.create(null);

    for (const row of optionRows.value) {
        const name = row.name.trim();
        const value = row.value.trim();

        if (!name || !value) {
            variantMessage.value = '規格名稱與內容不可空白';
            return null;
        }

        if (Object.prototype.hasOwnProperty.call(options, name)) {
            variantMessage.value = '規格名稱不可重複';
            return null;
        }

        options[name] = value;
    }

    return options;
}

// 新增或修改商品規格
function saveVariant(productId: number) {
    variantMessage.value = '';

    const options = buildOptions();

    if (!options) return;

    variantForm.options = options;

    if (editingVariantId.value !== null) {
        variantForm.put(
            `/products/${productId}/variants/${editingVariantId.value}`,
            {
                preserveScroll: true,
                onSuccess: () => resetVariantForm(),
            }
        );

        return;
    }

    variantForm.post(`/products/${productId}/variants`, {
        preserveScroll: true,
        onSuccess: () => resetVariantForm(),
    });
}

// 刪除商品規格
function deleteVariant(productId: number, variant: ProductVariant) {
    if (!confirm('確定刪除此商品規格嗎？')) {
        return;
    }

    router.delete(
        `/products/${productId}/variants/${variant.id}`,
        {
            preserveScroll: true,
            onSuccess: () => {
                if (editingVariantId.value === variant.id) {
                    resetVariantForm();
                }
            },
        }
    );
}

// 規格顯示文字
function variantLabel(options: Record<string, string>): string {
    return Object.entries(options)
        .map(([name, value]) => `${name}：${value}`)
        .join(' / ');
}

// 規格總庫存
function totalVariantStock(product: Product): number {
    return (product.variants ?? [])
        .filter(variant => Boolean(variant.is_active))
        .reduce((total, variant) => total + Number(variant.stock), 0);
}

// 釋放圖片預覽
onBeforeUnmount(() => {
    clearPreview(newImagePreview.value);
    clearPreview(editImagePreview.value);
});
</script>

<template>
    <Head title="商品管理" />

    <main class="product-page">
        <h1>商品管理</h1>

        <h2>新增商品</h2>

        <div v-if="sites.length === 0">
            目前沒有網站，請先建立網站。
            <a href="/sites/create">建立網站</a>
        </div>

        <form v-else class="product-form" @submit.prevent="submit">
            <div>
                <label>所屬網站</label>
                <select v-model="form.site_id" required>
                    <option value="" disabled>請選擇網站</option>
                    <option
                        v-for="site in sites"
                        :key="site.id"
                        :value="site.id"
                    >
                        {{ site.name }}
                    </option>
                </select>
                <p class="error">{{ form.errors.site_id }}</p>
            </div>

            <div>
                <label>商品名稱</label>
                <input v-model="form.name" type="text" required />
                <p class="error">{{ form.errors.name }}</p>
            </div>

            <div>
                <label>商品描述</label>
                <textarea v-model="form.description"></textarea>
                <p class="error">{{ form.errors.description }}</p>
            </div>

            <div>
                <label>商品價格</label>
                <input
                    v-model.number="form.price"
                    type="number"
                    min="0"
                    step="0.01"
                    required
                />
                <p class="error">{{ form.errors.price }}</p>
            </div>

            <div>
                <label>商品庫存</label>
                <input
                    v-model.number="form.stock"
                    type="number"
                    min="0"
                    required
                />
                <p class="error">{{ form.errors.stock }}</p>
            </div>

            <div>
                <label>商品圖片</label>
                <input
                    ref="imageInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    @change="onImageChange"
                />

                <p class="error">
                    {{ imageError || form.errors.image }}
                </p>

                <img
                    v-if="newImagePreview"
                    :src="newImagePreview"
                    class="preview-image"
                    alt="商品圖片預覽"
                />
            </div>

            <button type="submit" :disabled="form.processing">
                {{ form.processing ? '新增中...' : '新增商品' }}
            </button>
        </form>

        <hr />

        <h2>商品列表</h2>

        <p v-if="products.length === 0">目前沒有商品</p>

        <div v-else class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>圖片</th>
                        <th>商品名稱</th>
                        <th>價格</th>
                        <th>庫存</th>
                        <th>狀態</th>
                        <th>操作</th>
                    </tr>
                </thead>

                <tbody>
                    <template v-for="product in products" :key="product.id">
                        <tr>
                            <td>
                                <img
                                    v-if="product.image"
                                    :src="imageUrl(product.image) ?? undefined"
                                    :alt="product.name"
                                    class="product-image"
                                />
                                <span v-else>無圖片</span>
                            </td>

                            <td>{{ product.name }}</td>

                            <td>
                                <template v-if="product.variants?.length">
                                    多規格價格
                                </template>
                                <template v-else>
                                    NT$ {{ product.price }}
                                </template>
                            </td>

                            <td>
                                <template v-if="product.variants?.length">
                                    {{ totalVariantStock(product) }}
                                </template>
                                <template v-else>
                                    {{ product.stock }}
                                </template>
                            </td>

                            <td>
                                {{ product.is_active ? '上架' : '下架' }}
                            </td>

                            <td>
                                <div class="actions">
                                    <button
                                        type="button"
                                        @click="startEdit(product)"
                                    >
                                        編輯
                                    </button>

                                    <button
                                        type="button"
                                        @click="toggleActive(product)"
                                    >
                                        {{ product.is_active ? '下架' : '上架' }}
                                    </button>

                                    <button
                                        type="button"
                                        @click="toggleVariantManager(product)"
                                    >
                                        規格管理
                                    </button>

                                    <button
                                        type="button"
                                        class="danger-button"
                                        @click="deleteProduct(product)"
                                    >
                                        刪除
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- 商品編輯表單 -->
                        <tr v-if="editingId === product.id">
                            <td colspan="6">
                                <form
                                    class="product-form"
                                    @submit.prevent="saveEdit(product.id)"
                                >
                                    <h3>編輯商品</h3>

                                    <label>商品名稱</label>
                                    <input
                                        v-model="editForm.name"
                                        type="text"
                                        required
                                    />
                                    <p class="error">{{ editForm.errors.name }}</p>

                                    <label>商品描述</label>
                                    <textarea v-model="editForm.description"></textarea>
                                    <p class="error">{{ editForm.errors.description }}</p>

                                    <label>商品價格</label>
                                    <input
                                        v-model.number="editForm.price"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        required
                                    />
                                    <p class="error">{{ editForm.errors.price }}</p>

                                    <label>商品庫存</label>
                                    <input
                                        v-model.number="editForm.stock"
                                        type="number"
                                        min="0"
                                        required
                                    />
                                    <p class="error">{{ editForm.errors.stock }}</p>

                                    <label>商品圖片</label>
                                    <input
                                        ref="editImageInput"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        @change="onEditImageChange"
                                    />
                                    <p class="error">
                                        {{ editImageError || editForm.errors.image }}
                                    </p>

                                    <img
                                        v-if="editImagePreview"
                                        :src="editImagePreview"
                                        class="preview-image"
                                        alt="商品圖片預覽"
                                    />

                                    <label>
                                        <input
                                            v-model="editForm.is_active"
                                            type="checkbox"
                                        />
                                        商品上架
                                    </label>

                                    <div class="actions">
                                        <button
                                            type="submit"
                                            :disabled="editForm.processing"
                                        >
                                            儲存修改
                                        </button>

                                        <button
                                            type="button"
                                            @click="cancelEdit"
                                        >
                                            取消
                                        </button>
                                    </div>
                                </form>
                            </td>
                        </tr>

                        <!-- 商品規格管理 -->
                        <tr v-if="managingProductId === product.id">
                            <td colspan="6">
                                <section class="variant-panel">
                                    <h3>{{ product.name }}－規格管理</h3>

                                    <h4>目前規格列表</h4>

                                    <p v-if="!product.variants?.length">
                                        目前沒有規格，請先新增。
                                    </p>

                                    <div v-else class="table-wrapper">
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th>規格</th>
                                                    <th>SKU</th>
                                                    <th>價格</th>
                                                    <th>庫存</th>
                                                    <th>狀態</th>
                                                    <th>操作</th>
                                                </tr>
                                            </thead>

                                            <tbody>
                                                <tr
                                                    v-for="variant in product.variants"
                                                    :key="variant.id"
                                                >
                                                    <td>
                                                        {{ variantLabel(variant.options) }}
                                                    </td>

                                                    <td>{{ variant.sku || '未設定' }}</td>
                                                    <td>NT$ {{ variant.price }}</td>
                                                    <td>{{ variant.stock }}</td>
                                                    <td>
                                                        {{ variant.is_active ? '啟用' : '停用' }}
                                                    </td>

                                                    <td>
                                                        <div class="actions">
                                                            <button
                                                                type="button"
                                                                @click="startVariantEdit(variant)"
                                                            >
                                                                編輯
                                                            </button>

                                                            <button
                                                                type="button"
                                                                class="danger-button"
                                                                @click="deleteVariant(product.id, variant)"
                                                            >
                                                                刪除
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <hr />

                                    <h4>
                                        {{ editingVariantId ? '編輯規格' : '新增規格' }}
                                    </h4>

                                    <form
                                        class="product-form"
                                        @submit.prevent="saveVariant(product.id)"
                                    >
                                        <h4>規格組合</h4>

                                        <div
                                            v-for="(option, index) in optionRows"
                                            :key="index"
                                            class="option-row"
                                        >
                                            <input
                                                v-model="option.name"
                                                placeholder="規格名稱，例如：顏色"
                                                maxlength="50"
                                                required
                                            />

                                            <input
                                                v-model="option.value"
                                                placeholder="規格內容，例如：黑色"
                                                maxlength="100"
                                                required
                                            />

                                            <button
                                                type="button"
                                                :disabled="optionRows.length === 1"
                                                @click="removeOptionRow(index)"
                                            >
                                                移除
                                            </button>
                                        </div>

                                        <button type="button" @click="addOptionRow">
                                            ＋ 增加規格項目
                                        </button>

                                        <p class="error">{{ variantMessage }}</p>
                                        <p class="error">{{ variantForm.errors.options }}</p>

                                        <label>SKU（可不填）</label>
                                        <input
                                            v-model="variantForm.sku"
                                            type="text"
                                            maxlength="100"
                                            placeholder="例如：G502-BLACK"
                                        />
                                        <p class="error">{{ variantForm.errors.sku }}</p>

                                        <label>規格價格</label>
                                        <input
                                            v-model.number="variantForm.price"
                                            type="number"
                                            min="0"
                                            max="99999999.99"
                                            step="0.01"
                                            required
                                        />
                                        <p class="error">{{ variantForm.errors.price }}</p>

                                        <label>規格庫存</label>
                                        <input
                                            v-model.number="variantForm.stock"
                                            type="number"
                                            min="0"
                                            max="4294967295"
                                            step="1"
                                            required
                                        />
                                        <p class="error">{{ variantForm.errors.stock }}</p>

                                        <label>
                                            <input
                                                v-model="variantForm.is_active"
                                                type="checkbox"
                                            />
                                            啟用此規格
                                        </label>

                                        <div class="actions">
                                            <button
                                                type="submit"
                                                :disabled="variantForm.processing"
                                            >
                                                {{
                                                    variantForm.processing
                                                        ? '儲存中...'
                                                        : editingVariantId
                                                            ? '儲存規格修改'
                                                            : '新增規格'
                                                }}
                                            </button>

                                            <button
                                                type="button"
                                                @click="resetVariantForm"
                                            >
                                                清空／取消編輯
                                            </button>

                                            <button
                                                type="button"
                                                @click="toggleVariantManager(product)"
                                            >
                                                關閉規格管理
                                            </button>
                                        </div>
                                    </form>
                                </section>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </main>
</template>

<style scoped>
.product-page {
    max-width: 1200px;
    margin: 40px auto;
    padding: 20px;
}

.product-form {
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-width: 650px;
}

.product-form input:not([type="checkbox"]),
.product-form select,
.product-form textarea {
    padding: 9px;
    border: 1px solid #ccc;
    border-radius: 5px;
    width: 100%;
    box-sizing: border-box;
}

.product-form textarea {
    min-height: 80px;
}

.product-form label {
    font-weight: 600;
}

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

table {
    border-collapse: collapse;
    width: 100%;
    margin-top: 15px;
}

th,
td {
    border: 1px solid #ddd;
    padding: 12px;
    text-align: left;
    vertical-align: middle;
}

th {
    background-color: #f4f4f4;
}

button {
    padding: 8px 12px;
    border: 1px solid #ccc;
    border-radius: 5px;
    cursor: pointer;
    background-color: #f3f3f3;
}

button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.danger-button {
    color: #b42318;
}

.product-image {
    width: 80px;
    height: 80px;
    object-fit: cover;
}

.preview-image {
    width: 150px;
    height: 150px;
    object-fit: cover;
    margin-top: 10px;
}

.variant-panel {
    background-color: #fafafa;
    padding: 20px;
    border-radius: 8px;
}

.option-row {
    display: flex;
    gap: 10px;
    align-items: center;
}

.option-row input {
    flex: 1;
    min-width: 0;
}

.error {
    color: #c62828;
    font-size: 13px;
    margin: 0;
}

hr {
    margin: 30px 0;
    border: none;
    border-top: 1px solid #ddd;
}

@media (max-width: 768px) {
    .product-page {
        margin: 10px auto;
        padding: 12px;
    }

    .option-row {
        flex-direction: column;
        align-items: stretch;
    }

    th,
    td {
        padding: 8px;
    }
}
</style>
