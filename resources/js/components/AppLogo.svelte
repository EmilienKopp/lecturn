<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';

    const appName = $derived((page.props.name as string) ?? 'Tecturn');
    $inspect(page.props);
</script>

<div
    class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
>
    <AppLogoIcon class="size-5 fill-current" />
</div>
<div class="ml-1 grid flex-1 text-left text-sm">
    <span class="mb-0.5 truncate font-display leading-tight font-semibold"
        >{appName}</span
    >
</div>
