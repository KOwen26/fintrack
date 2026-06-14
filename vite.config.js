import inertia from '@inertiajs/vite';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            // ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        inertia({ ssr: false }),
        tailwindcss(),
        svelte(),
    ],
    resolve: {
        tsconfigPaths: true,
    },
    // optimizeDeps: {
    //     exclude: [
    //         '/node_modules/.vite/deps/Deferred.svelte',
    //         '/node_modules/.vite/deps/Link.svelte',
    //         '/node_modules/.vite/deps/WhenVisible.svelte',
    //         '/node_modules/.vite/deps/Render.svelte',
    //         '/node_modules/.vite/deps/App.svelte',
    //     ],
    // },
});
