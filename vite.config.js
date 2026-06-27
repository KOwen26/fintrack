import fs from 'fs';
import path from 'path';

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
        inertia({
            // ssr: false
        }),
        tailwindcss(),
        svelte(),
    ],
    resolve: {
        tsconfigPaths: true,
    },
    server: {
        watch: {
            ignored: getGitignorePatterns(),
        },
    },
});

// Helper function to read and format .gitignore paths for Chokidar
function getGitignorePatterns() {
    try {
        const gitignorePath = path.resolve(process.cwd(), '.gitignore');
        if (!fs.existsSync(gitignorePath)) return [];

        const contents = fs
            .readFileSync(gitignorePath, 'utf-8')
            .split(/\r?\n/)
            .map((line) => line.trim())
            // Filter out comments and empty lines
            .filter((line) => line && !line.startsWith('#'))
            // Convert to Chokidar-compatible globs
            .map((pattern) => {
                // If it targets a folder or a generic name without an extension
                if (pattern.endsWith('/') || !pattern.includes('.')) {
                    const clean = pattern.replace(/^\/|\/$/g, ''); // strip slashes

                    return `**/${clean}/**`;
                }

                // For file extensions or specific files (e.g., *.log, .env.local)
                return pattern.startsWith('/') ? pattern.slice(1) : `**/${pattern}`;
            });

        return contents;
    } catch (e) {
        console.warn('Failed to parse .gitignore for Vite watcher:', e);

        return [];
    }
}
