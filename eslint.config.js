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

// ── personal-svelte/sort-tags ────────────────────────────────────────────────
// Enforces top-level block order in .svelte files:
//   module <script> → <script> → markup → {#snippet} → <style>
const sortTagsRule = {
    meta: {
        type: 'suggestion',
        docs: { description: 'Enforce top-level block order in Svelte components.' },
        fixable: 'code',
        schema: [],
    },
    create(context) {
        const isModuleScript = (node) =>
            (node.startTag?.attributes ?? []).some((attr) => {
                const name = attr.key?.name;
                const value = String(
                    Array.isArray(attr.value) ? (attr.value[0]?.value ?? '') : (attr.value ?? '')
                );

                return name === 'module' || (name === 'context' && value === 'module');
            });

        const rankOf = (node) => {
            switch (node.type) {
                case 'SvelteScriptElement':
                    return isModuleScript(node) ? 0 : 1;
                case 'SvelteSnippetBlock':
                    return 3;
                case 'SvelteStyleElement':
                    return 4;
                default:
                    return 2; // markup
            }
        };

        // Spacers = whitespace text nodes and HTML comments. They don't carry
        // their own rank — they inherit the preceding block's rank so they
        // never trigger a violation on their own.
        const isSpacer = (node) =>
            node.type === 'SvelteHTMLComment' || (node.type === 'SvelteText' && !node.value.trim());

        return {
            Program(program) {
                // Only check the meaningful (non-spacer) nodes.
                const meaningful = program.body.filter((node) => !isSpacer(node));
                if (meaningful.length < 2) return;

                const ranks = meaningful.map(rankOf);
                if (ranks.every((r, i) => i === 0 || ranks[i - 1] <= r)) return;

                const offender = meaningful.find((node, i) => i > 0 && ranks[i - 1] > ranks[i]);

                context.report({
                    node: offender ?? meaningful[0],
                    message:
                        'Expected top-level block order: module script, script, markup, snippets, style.',
                    fix: (fixer) => {
                        // Sort only the meaningful nodes by rank, then rebuild the
                        // entire top-level span. Scripts stay in place because their
                        // rank (0/1) sorts them first — but we preserve their text
                        // verbatim so inner fixes (import sorting etc.) don't clash.
                        const sorted = meaningful
                            .map((node) => ({ node, rank: rankOf(node) }))
                            .sort((a, b) => a.rank - b.rank)
                            .map((entry) => context.sourceCode.getText(entry.node));

                        return fixer.replaceTextRange(
                            [meaningful[0].range[0], meaningful[meaningful.length - 1].range[1]],
                            sorted.join('\n\n')
                        );
                    },
                });
            },
        };
    },
};

const personalSveltePlugin = { rules: { 'sort-tags': sortTagsRule } };
// ─────────────────────────────────────────────────────────────────────────────

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
            'svelte/no-unused-props': 'off',
            'svelte/no-unused-svelte-ignore': 'warn',
            'svelte/no-at-html-tags': 'warn',
            'svelte/no-useless-children-snippet': 'warn',
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
                        // `labelClass, contentClass, etc.` directives. (Alphabetical order within the same group.)
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
    {
        name: 'Personal Svelte Rules',
        files: ['resources/**/*.svelte'],
        plugins: { 'personal-svelte': personalSveltePlugin },
        rules: {
            'personal-svelte/sort-tags': 'error',
        },
    },
]);
