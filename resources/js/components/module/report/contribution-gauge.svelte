<script lang="ts">
    interface Member {
        name: string;
        contributed: number;
        percentage: number;
    }

    let { members, total }: { members: Member[]; total: number } = $props();

    // Assign a DaisyUI color class per member position (predictable order)
    const memberColors = ['bg-primary', 'bg-secondary', 'bg-accent', 'bg-info', 'bg-success'];

    function formatIDR(value: number): string {
        return value.toLocaleString('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        });
    }
</script>

{#if members.length === 0}
    <div class="flex flex-col items-center justify-center py-8 text-base-content/40">
        <i class="iconify mb-2 size-8 ph--users-bold"></i>
        <p class="text-sm">No income recorded this period</p>
    </div>
{:else}
    <!-- Stacked bar -->
    <div class="flex h-6 w-full overflow-hidden rounded-full">
        {#each members as member, i (member.name)}
            <div
                style="width: {member.percentage}%"
                class="{memberColors[i % memberColors.length]} transition-all"
                title="{member.name}: {member.percentage}%">
            </div>
        {/each}
    </div>

    <!-- Legend -->
    <ul class="mt-4 space-y-2">
        {#each members as member, i (member.name)}
            <li class="flex items-center justify-between text-sm">
                <div class="flex items-center gap-2">
                    <span
                        class="inline-block size-2.5 rounded-full {memberColors[
                            i % memberColors.length
                        ]}"></span>
                    <span class="font-medium">{member.name}</span>
                </div>
                <div class="text-right">
                    <span class="font-mono">{formatIDR(member.contributed)}</span>
                    <span class="ml-1 text-base-content/50">{member.percentage}%</span>
                </div>
            </li>
        {/each}
    </ul>

    <p class="mt-3 text-right text-xs text-base-content/40">
        Total: {formatIDR(total)}
    </p>
{/if}
