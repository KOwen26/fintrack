<script lang="ts">
    import type { Snippet } from 'svelte';
    import type { HTMLAttributes } from 'svelte/elements';

    import { useFlashToast } from '@hooks/flash-handler.svelte';

    import Toaster from '@components/ui/toaster.svelte';

    type Props = HTMLAttributes<HTMLDivElement> & {
        children?: Snippet;
        reverse?: boolean;
    };

    let { children, reverse = false, ...props }: Props = $props();

    useFlashToast();
</script>

<svelte:head>
    <style>
        body {
            background-color: #f3f4f6; /* gray-100 */
        }
    </style>
</svelte:head>

<Toaster />
<div class="flex min-h-screen items-center justify-center p-4 sm:p-6 lg:p-8">
    <div
        class="flex w-full max-w-5xl flex-col overflow-hidden rounded-lg bg-white shadow-2xl ring-1 ring-gray-900/5 {reverse
            ? 'lg:flex-row-reverse'
            : 'lg:flex-row'}">
        <!-- Form Section -->
        <div class="flex w-full flex-col justify-center p-8 sm:p-12 lg:w-1/2 lg:p-16">
            <!-- App Logo/Icon -->
            <div class="mb-8 flex justify-center lg:justify-start">
                <div
                    class="inline-flex items-center justify-center rounded-md bg-gray-50 p-3 shadow-xs ring-1 ring-gray-900/5">
                    <!-- Beaker icon -->
                    <svg
                        class="size-10 text-primary"
                        viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg">
                        <path
                            class="opacity-40"
                            d="M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"
                            fill="currentColor" />
                        <path
                            d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"
                            fill="none"
                            stroke="currentColor"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2" />
                    </svg>
                </div>
            </div>

            <!-- Form Content via Slots -->
            {@render children?.()}
        </div>

        <!-- Decorative Branding Section -->
        <div
            class="relative hidden bg-primary p-12 text-white lg:flex lg:w-1/2 lg:flex-col lg:justify-between">
            <!-- Abstract gradient overlay -->
            <div
                class="absolute inset-0 bg-linear-to-br from-primary-500 via-primary-700 to-primary-950">
            </div>

            <!-- Decorative blurred shapes -->
            <div
                class="absolute -top-32 -right-32 size-96 rounded-full bg-white opacity-10 blur-3xl">
            </div>
            <div
                class="absolute -bottom-32 -left-32 size-96 rounded-full bg-white opacity-10 blur-3xl">
            </div>

            <div class="relative z-10 mt-8">
                <h2 class="mb-6 text-4xl font-bold tracking-tight text-white">Pro Formula</h2>
                <p class="text-lg leading-relaxed text-white/80">
                    The ultimate platform for product formulation and data management. Streamline
                    your entire workflow with precision and speed.
                </p>
            </div>

            <!-- Testimonial or Branding Footer -->
            <div class="relative z-10 mt-auto border-t border-white/20 pt-8">
                <blockquote class="text-sm leading-relaxed font-medium text-white/90 italic">
                    "This system completely revolutionized how we handle our formulations, bringing
                    unprecedented accuracy and efficiency to our team."
                </blockquote>
            </div>
        </div>
    </div>
</div>
