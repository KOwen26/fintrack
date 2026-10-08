<script lang="ts">
    import type { Snippet } from 'svelte';
    import type { HTMLAttributes } from 'svelte/elements';

    import { useFlashToast } from '@lib/toast-handler.svelte';

    import AppIcon from '@components/ui/app-icon.svelte';
    import Toaster from '@components/ui/toaster.svelte';

    type Props = HTMLAttributes<HTMLDivElement> & {
        children?: Snippet;
        reverse?: boolean;
    };

    let { children, reverse = false, ...props }: Props = $props();

    useFlashToast();
</script>

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
                <AppIcon />
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
                <h2 class="mb-6 text-4xl font-bold tracking-tight text-white">Fintrack</h2>
                <p class="text-lg leading-relaxed text-white/80">
                    The ultimate platform for finance tracking and budget management. Streamline
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
