import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules/vue/') || id.includes('node_modules/@vue/')) {
                        return 'vue-vendor';
                    }
                    if (id.includes('node_modules/@inertiajs/')) {
                        return 'inertia-vendor';
                    }
                    if (id.includes('node_modules/chart.js/')) {
                        return 'chart-vendor';
                    }
                    if (id.includes('node_modules/html2canvas/')) {
                        return 'html2canvas';
                    }
                    if (id.includes('node_modules/@vueup/vue-quill/') || id.includes('node_modules/quill/')) {
                        return 'quill';
                    }
                },
            },
        },
    },
});
