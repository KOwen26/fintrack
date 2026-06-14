<script lang="ts">
    
import { inertia } from '@inertiajs/svelte';
    import auth from '@wayfinder/routes/auth';

    import BaseLayout from '@components/layouts/base-layout.svelte';
</script>

<BaseLayout>
    <p>Welcome to Laravel Inertia Svelte</p>
    <div>
        <button class="btn btn-wide btn-neutral" use:inertia={{ href: auth.register().url }}>
            Register</button>
        <button class="btn btn-wide btn-primary" use:inertia={{ href: auth.login().url }}>
            Login</button>
    </div>
</BaseLayout>
