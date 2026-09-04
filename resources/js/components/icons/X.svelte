<script lang="ts">
    let {
        size = 24,
        class: className = '',
        ...rest
    }: {
        size?: number | string;
        class?: string;
        [key: string]: unknown;
    } = $props();
</script>

<svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 300 271"
    width={size}
    height={size}
    fill="currentColor"
    class={className}
    {...rest}
>
    <path
        d="m236 0h46l-101 115 118 156h-92.6l-72.5-94.8-83 94.8h-46l107-123-113-148h94.9l65.5 86.6zm-16.1 244h25.5l-165-218h-27.4z"
    />
</svg>
