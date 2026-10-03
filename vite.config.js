import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        cors: { origin: process.env.APP_URL || 'http://localhost:8088' },
        origin: `http://localhost:${process.env.VITE_PORT || 5178}`,
        hmr: { host: 'localhost', clientPort: Number(process.env.VITE_PORT || 5178) },
        watch: { ignored: ['**/storage/framework/views/**'] },
    },
});
