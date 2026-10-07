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
    // Vendor pihak-ketiga (axios/alpine) jarang berubah: dipisah agar cache
    // browser antar deploy tetap kena. Tanpa mengubah urutan eksekusi modul
    // (tidak ada dependency sirkular dengan kode aplikasi).
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    vendor: ['axios', 'alpinejs', '@alpinejs/persist', '@alpinejs/collapse'],
                },
            },
        },
    },
});
