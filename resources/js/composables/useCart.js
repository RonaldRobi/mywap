import { ref, computed, watch } from 'vue';

const STORAGE_KEY = 'mywap_cart';

const items = ref(loadCart());

function loadCart() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : [];
    } catch {
        return [];
    }
}

function saveCart() {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(items.value));
    } catch {}
}

watch(items, saveCart, { deep: true });

export function useCart() {
    const count = computed(() => items.value.reduce((sum, i) => sum + i.quantity, 0));
    const total = computed(() => items.value.reduce((sum, i) => sum + i.price * i.quantity, 0));
    const combinedPostage = computed(() => {
        const postages = items.value.map(i => i.postage_cost || 0);
        return postages.length ? Math.max(...postages) : 0;
    });
    const grandTotal = computed(() => total.value + combinedPostage.value);

    function add(product, quantity = 1, options = {}) {
        const key = `${product.id}-${JSON.stringify(options)}`;
        const existing = items.value.find(i => i.key === key);
        if (existing) {
            existing.quantity += quantity;
        } else {
            items.value.push({
                key,
                id: product.id,
                name: product.name,
                price: Number(options.price ?? product.price),
                member_price: product.member_price != null ? Number(product.member_price) : null,
                image: product.image || (product.images?.[0]) || null,
                postage_cost: Number(product.postage_cost || 0),
                quantity,
                variation: options.variation || null,
                variation_option_id: options.variation_option_id || null,
                variation_snapshot: options.variation_snapshot || null,
                stock: options.option_stock ?? product.stock,
            });
        }
    }

    function remove(key) {
        items.value = items.value.filter(i => i.key !== key);
    }

    function updateQuantity(key, quantity) {
        const item = items.value.find(i => i.key === key);
        if (item) {
            item.quantity = Math.max(1, Math.min(quantity, item.stock || 999));
        }
    }

    function clear() {
        items.value = [];
    }

    return { items, count, total, combinedPostage, grandTotal, add, remove, updateQuantity, clear };
}
