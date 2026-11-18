import { onUnmounted, ref } from 'vue';
import axios from 'axios';

export function useMemberSearch(options = {}) {
    const minimumLength = options.minimumLength ?? 2;
    const debounceMs = options.debounceMs ?? 300;
    const query = ref('');
    const results = ref([]);
    const loading = ref(false);
    const error = ref('');
    const open = ref(false);
    let timer;
    let controller;

    function clear() {
        clearTimeout(timer);
        controller?.abort();
        controller = null;
        query.value = '';
        results.value = [];
        error.value = '';
        loading.value = false;
        open.value = false;
    }

    function search(value) {
        const normalized = value.trim();
        query.value = value;
        error.value = '';
        clearTimeout(timer);
        controller?.abort();

        if (normalized.length < minimumLength) {
            results.value = [];
            loading.value = false;
            open.value = false;
            return;
        }

        timer = setTimeout(async () => {
            const activeController = new AbortController();
            controller = activeController;
            loading.value = true;

            try {
                const response = await axios.get('/api/members/search', {
                    params: { q: normalized, ...(options.params?.() ?? {}) },
                    signal: activeController.signal,
                });
                results.value = response.data || [];
                open.value = true;
            } catch (requestError) {
                if (requestError.code === 'ERR_CANCELED') return;
                results.value = [];
                error.value = requestError?.response?.status === 429
                    ? 'Terlalu banyak carian. Sila tunggu sebentar dan cuba lagi.'
                    : 'Carian gagal. Sila cuba lagi.';
                open.value = true;
            } finally {
                if (controller === activeController && !activeController.signal.aborted) loading.value = false;
            }
        }, debounceMs);
    }

    onUnmounted(() => {
        clearTimeout(timer);
        controller?.abort();
    });

    return { query, results, loading, error, open, search, clear };
}
