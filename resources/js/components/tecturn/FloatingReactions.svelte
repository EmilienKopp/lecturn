<script lang="ts">
    let { enabled = false }: { enabled?: boolean } = $props();

    type Particle = { id: number; emoji: string; x: number };

    let particles = $state<Particle[]>([]);
    let counter = 0;

    export function spawnReaction(emoji: string): void {
        if (!enabled) {
            return;
        }

        const id = ++counter;
        const x = 10 + Math.random() * 80;

        particles = [...particles, { id, emoji, x }];

        setTimeout(() => {
            particles = particles.filter((p) => p.id !== id);
        }, 3000);
    }
</script>

{#if enabled}
    <div
        class="pointer-events-none absolute inset-0 z-[9999] overflow-hidden"
        aria-hidden="true"
    >
        {#each particles as particle (particle.id)}
            <span
                class="float-emoji absolute bottom-0 select-none text-4xl"
                style="left: {particle.x}%"
            >
                {particle.emoji}
            </span>
        {/each}
    </div>
{/if}

<style>
    @keyframes float-up {
        0% {
            transform: translateY(0) scale(1);
            opacity: 1;
        }
        80% {
            opacity: 0.8;
        }
        100% {
            transform: translateY(-80vh) scale(1.4);
            opacity: 0;
        }
    }

    .float-emoji {
        animation: float-up 3s ease-out forwards;
    }

    @media (prefers-reduced-motion: reduce) {
        .float-emoji {
            animation: none;
            opacity: 0;
        }
    }
</style>
