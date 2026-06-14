<script lang="ts">
    const author = import.meta.env.VITE_AUTHOR || undefined;
    const currentYear = new Date().getFullYear();
</script>

<footer class="px-6 py-4">
    <p class="text-sm">
        Copyright &copy; {currentYear}

        {@render authoredBy(author)}
    </p>
</footer>

{#snippet authoredBy(author: string)}
    {#if author}
        <span class="text-accent font-medium">by {author}</span>
    {/if}
{/snippet}
