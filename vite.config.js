import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    server: {
        proxy: {
            // --- CAPA DE PROXY PARA CHATWOOT (Igual a la de Next.js) ---
            '/chatwoot-proxy': {
                target: 'https://chatwoot.servilutioncrm.cloud',
                changeOrigin: true,
                rewrite: (path) => path.replace(/^\/chatwoot-proxy/, ''),
                cookieDomainRewrite: "localhost",
            },
            '/cable': {
                target: 'https://chatwoot.servilutioncrm.cloud',
                ws: true,
                changeOrigin: true,
            },
            '/api/v1': {
                target: 'https://chatwoot.servilutioncrm.cloud',
                changeOrigin: true,
            },
            '/auth': {
                target: 'https://chatwoot.servilutioncrm.cloud',
                changeOrigin: true,
            },
            '/app': {
                target: 'https://chatwoot.servilutioncrm.cloud',
                changeOrigin: true,
            },
            '/rails': {
                target: 'https://chatwoot.servilutioncrm.cloud',
                changeOrigin: true,
            },
            '/assets': {
                target: 'https://chatwoot.servilutioncrm.cloud',
                changeOrigin: true,
            },
            '/vite': {
                target: 'https://chatwoot.servilutioncrm.cloud',
                changeOrigin: true,
            }
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
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
});
