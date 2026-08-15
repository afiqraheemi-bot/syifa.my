import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/app.js',
                'resources/css/public-website.css',
                'resources/js/public-website.js',
                'resources/js/public-content-enhancements.js',
                'resources/js/blog-slider.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
        vue(),
    ],
    server: {
        host: '127.0.0.1',
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
