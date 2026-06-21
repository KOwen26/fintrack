import { fileURLToPath } from 'node:url';

import svelteConfig from './svelte.config.js';

import { includeIgnoreFile } from '@eslint/compat';
import js from '@eslint/js';
import stylistic from '@stylistic/eslint-plugin';
import prettier from 'eslint-config-prettier';
import svelte from 'eslint-plugin-svelte';
import unusedImports from 'eslint-plugin-unused-imports';
import { defineConfig, globalIgnores } from 'eslint/config';
import globals from 'globals';
import svelteParser from 'svelte-eslint-parser';
import ts from 'typescript-eslint';

const gitignorePath = fileURLToPath(new URL('./.gitignore', import.meta.url));

export default defineConfig([
    includeIgnoreFile(gitignorePath),
    globalIgnores(
        [
            'pnpm-lock.yaml',
            'package-lock.json',
            'yarn.lock',

            'node_modules',
            'vendor',

            'bootstrap/ssr',
            'public',
            'storage',

            'resources/js/wayfinder',
        ],
        'Global Ignore'
    ),
    js.configs.recommended,
    ...ts.configs.recommended,
    prettier,
    //   stylistic.configs.recommended,
    ...svelte.configs.recommended,
    ...svelte.configs.prettier,
    {
        files: [
            'resources/**/*.{svelte,svelte.js,svelte.ts,js,cjs,mjs,ts,mts,cts}',
            'eslint.config.js',
            'vite.config.js',
        ],
        plugins: { 'unused-imports': unusedImports, '@stylistic': stylistic },
        languageOptions: {
            globals: globals.browser,
            // App: 'readonly',
        },
        rules: {
            // typescript-eslint strongly recommend that you do not use the no-undef lint rule on TypeScript projects.
            // see: https://typescript-eslint.io/troubleshooting/faqs/eslint/#i-get-errors-from-the-no-undef-rule-about-global-variables-not-being-defined-even-though-there-are-no-typescript-errors
            'no-undef': 'off',
            'no-useless-assignment': 'warn',
            'no-unused-vars': 'warn',
            'unused-imports/no-unused-imports': 'warn',
            '@stylistic/padding-line-between-statements': [
                'warn',
                { blankLine: 'always', prev: '*', next: 'return' }, //Give a blank line before return statement
                { blankLine: 'always', prev: 'export', next: 'export' }, //Give a blank line between export statement
            ],

            '@typescript-eslint/no-unused-vars': 'warn',
            '@typescript-eslint/no-explicit-any': 'warn',
            '@typescript-eslint/no-unused-expressions': 'warn',
        },
    },
    {
        name: 'Svelte Rules',
        files: ['resources/**/*.{svelte,svelte.js,svelte.ts}'],
        languageOptions: {
            parser: svelteParser,
            parserOptions: {
                projectService: true,
                extraFileExtensions: ['.svelte'],
                parser: ts.parser,
                svelteConfig,
            },
        },
        rules: {
            'svelte/no-unused-props': 'warn',
            'svelte/no-unused-svelte-ignore': 'warn',
            'svelte/no-at-html-tags': 'warn',
            'svelte/sort-attributes': [
                'warn',
                {
                    order: [
                        // `this` property.
                        'this',
                        // `bind:this` directive.
                        'bind:this',
                        // `id` attribute.
                        'id',
                        // `name` attribute.
                        'name',
                        // ? custom attribute for input component
                        'maskName',
                        // `slot` attribute.
                        'slot',
                        'data-slot',
                        // `--style-props` (Alphabetical order within the same group.)
                        { match: '/^--/u', sort: 'alphabetical' },
                        // `style` attribute, and `style:` directives.
                        ['style', '/^style:/u'],
                        // `class` attribute.
                        'class',
                        // `class:` directives. (Alphabetical order within the same group.)
                        { match: '/^class:/u', sort: 'alphabetical' },
                        // `labelClass, wrapperClass, etc.` directives. (Alphabetical order within the same group.)
                        { match: '/.*Class$/u', sort: 'alphabetical' },
                        // other attributes. (Alphabetical order within the same group.)
                        {
                            match: ['!/:/u', '!/^(?:this|id|name|style|class)$/u', '!/^--/u'],
                            sort: 'alphabetical',
                        },
                        // `bind:` directives (other then `bind:this`), and `on:` directives.
                        ['/^on/u', '/^on:/u', '/^bind:/u', '!bind:this'],
                        // `use:` directives. (Alphabetical order within the same group.)
                        { match: '/^use:/u', sort: 'alphabetical' },
                        // `transition:` directive.
                        { match: '/^transition:/u', sort: 'alphabetical' },
                        // `in:` directive.
                        { match: '/^in:/u', sort: 'alphabetical' },
                        // `out:` directive.
                        { match: '/^out:/u', sort: 'alphabetical' },
                        // `animate:` directive.
                        { match: '/^animate:/u', sort: 'alphabetical' },
                        // `let:` directives. (Alphabetical order within the same group.)
                        { match: '/^let:/u', sort: 'alphabetical' },
                    ],
                },
            ],
        },
    },
]);
