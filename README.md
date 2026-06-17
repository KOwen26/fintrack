# Laravel Inertia Svelte

## Table of Contents

- [Laravel Inertia Svelte](#laravel-inertia-svelte)
    - [Table of Contents](#table-of-contents)
    - [Project Structure](#project-structure)
    - [Commands](#commands)
    - [Backend](#backend)
    - [Frontend](#frontend)
    - [Format, Lint, \& Static Code Analysis](#format-lint--static-code-analysis)
    - [Logging](#logging)
    - [Testing](#testing)

## Project Structure

### Backend (Laravel)

The backend follows the standard Laravel directory structure with some key additions:

- **app/Data**: Contains [Spatie Laravel Data](https://spatie.be/docs/laravel-data) objects, acting as Data Transfer Objects (DTOs) for robust data handling and type safety.
- **app/Enums**: PHP Enums used throughout the application.
- **routes**: Routes are organized into separate files for better maintainability:
    - `web.php`: Standard web routes.
    - `auth.php`: Authentication-related routes.
    - `dev.php`: Development-only routes (guarded by `OnlyDevelopment` middleware).
    - `settings.php`: Application settings routes.

### Frontend (Svelte 5 & Inertia.js)

The frontend is built with Svelte 5 and Inertia.js, located in `resources/js`:

- **components**: Reusable Svelte components, organized by category:
    - `ui`: Base UI components (often from shadcn-svelte or daisyUI), e.g., buttons, inputs, cards.
    - `layouts`: Layout components that wrap pages (e.g., `dashboard-layout.svelte`, `guest-layout.svelte`).
    - `data`: Components specifically designed for data presentation, such as data tables.
    - `menu`: Components related to menu rendering.
    - `navigation`: Navigation-related components like sidebars or breadcrumbs.
- **pages**: Inertia page components. These correspond to the views returned by Laravel controllers.
- **svelte**: Contains Svelte 5 specific logic:
    - `actions`: Custom Svelte actions (directives applied to elements).
    - `states`: Global state management stores using Svelte 5 runes (e.g., `is-mobile.svelte.ts`).
- **types**: TypeScript type definitions.
    - `generated.d.ts`: Auto-generated types from Laravel Data objects using [Laravel TypeScript Transformer](https://spatie.be/docs/typescript-transformer), ensuring type consistency between backend and frontend.
    - `inertia.d.ts`: Type definitions for Inertia.js specific props and helpers.
    - `index.ts`: Central export file for types.
- **utilities**: Helper functions and classes.
    - Files ending in `.svelte.ts` (e.g., `authorization.svelte.ts`, `datatable.svelte.ts`) contain Svelte 5 reactive logic (runes).
    - General utility files (e.g., `formatter.ts`, `shadcn.ts`) provide non-reactive helper functions.
- **data**: Static data or configuration files (e.g., menu structure, theme settings).

### Frontend Aliases

The project is configured with several Vite aliases to simplify imports:

- `@`: `resources/js`
- `@components`: `resources/js/components`
- `@layouts`: `resources/js/components/layouts`
- `@states`: `resources/js/svelte/states`
- `@utilities`: `resources/js/utilities`
- `@data`: `resources/js/data`
- `@type`: `resources/js/types`
- `@schema`: `resources/js/schema`
- `@route`: `resources/js/route.ts`

## Commands

- Run Development Server (Customized)
    - Prune telescope data
    - Run pnpm dev

```shell
    composer run dev
```

- Lint Laravel Backend (Dirty Changes [Require Version Control]) and Svelte Frontend

```shell
    composer run lint
```

- Format Laravel Backend (Dirty Changes [Require Version Control])

```shell
    composer run format
```

- Format Svelte Frontend

```shell
    pnpm run format
```

- Generate Typescript form Laravel Data

```shell
    composer run generate:ts //or php artisan typescript:transform
```

## Backend

Powered by laravel ecosystem, and Enhanced by:

- [Laravel Data](<[https://](https://spatie.be/docs/laravel-data/v4/getting-started/quickstart)>) Laravel DTO Mapper by Spatie
- [Laravel Typescript Transformer](<[https://](https://spatie.be/docs/typescript-transformer/v2/laravel/installation-and-setup)>) PHP class to typescript type by Spatie

```shell
  composer require spatie/laravel-data spatie/laravel-typescript-transformer --dev
```

## Frontend

- [Tailwindcss v4](https://tailwindcss.com)

- [Inertia v3](https://inertiajs.com/)

```shell
    composer require inertiajs/inertia-laravel


    pnpm add @inertiajs/svelte
```

- [Svelte v5](https://svelte.dev/)
    - [svelte-check](https://github.com/sveltejs/language-tools): A tool to check your Svelte code for errors.
    - [@sveltejs/vite-plugin-svelte](https://github.com/sveltejs/vite-plugin-svelte): Vite plugin for Svelte.

```shell
    pnpm add svelte svelte-check @sveltejs/vite-plugin-svelte -D
```

- Typescript
    - [Typescript](https://www.typescriptlang.org/): A strongly typed programming language that builds on JavaScript.

```shell
    pnpm add typescript -D
```

## Format, Lint, & Static Code Analysis

- [Pint](https://github.com/laravel/pint)
- [Rector](https://github.com/rectorphp/rector)

- Prettier
    - [Prettier](https://prettier.io/): An opinionated code formatter.
    - [prettier-plugin-svelte](https://github.com/sveltejs/prettier-plugin-svelte): Prettier plugin for Svelte.
    - [prettier-plugin-tailwindcss](https://github.com/tailwindlabs/prettier-plugin-tailwindcss): Prettier plugin for Tailwind CSS.
    - [@ianvs/prettier-plugin-sort-imports](https://github.com/ianvs/prettier-plugin-sort-imports): Prettier plugin to sort imports.

```shell
    pnpm add prettier prettier-plugin-svelte  prettier-plugin-tailwindcss @ianvs/prettier-plugin-sort-imports -D
```

- Eslint
    - [Eslint](https://eslint.org/): A tool for identifying and fixing problems in JavaScript code.
    - [@eslint/compat](https://github.com/eslint/eslint-compat): Compatibility package for ESLint.
    - [globals](https://github.com/sindresorhus/globals): Global variables for various environments.
    - [typescript-eslint](https://github.com/typescript-eslint/typescript-eslint): TypeScript plugin for ESLint.
    - [eslint-plugin-svelte](https://github.com/sveltejs/eslint-plugin-svelte): ESLint plugin for Svelte.
    - [svelte-eslint-parser](https://github.com/sveltejs/svelte-eslint-parser): ESLint parser for Svelte.
    - [eslint-config-prettier](https://github.com/prettier/eslint-config-prettier): Turns off all rules that are unnecessary or might conflict with Prettier.
    - [eslint-plugin-unused-imports](https://github.com/sweepline/eslint-plugin-unused-imports): ESLint plugin to remove unused imports.

```shell
    pnpm add eslint @eslint/compat globals  typescript-eslint eslint-plugin-svelte svelte-eslint-parser eslint-config-prettier eslint-plugin-unused-imports -D
```

## Logging

- [Laravel Telescope](https://laravel.com/docs/telescope): Debug assistant for Laravel applications.
- [Laravel Debugbar](https://github.com/barryvdh/laravel-debugbar): Integrates PHP Debug Bar with Laravel.

```shell
    composer require laravel/telescope --dev barryvdh/laravel-debugbar --dev

    php artisan telescope:install

    php artisan migrate
```

## Testing

- [Pest](https://pestphp.com/): A testing framework with a focus on simplicity.

```shell
    composer require pestphp/pest --dev

    ./vendor/bin/pest --init
```
