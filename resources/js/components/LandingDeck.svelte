<script lang="ts">
    import { Code, Presentation, Slide, Transition } from '@animotion/core';
    import '@animotion/core/theme';

    // The landing hero is a hand-written Animotion deck, not a Presenter
    // instance — the marketing copy lives here as slides, and the deck runs
    // embedded inside the page instead of owning the viewport.
    const reducedMotion =
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const morphPages = [
        `function talk() {
  return slides
}`,
        `function talk() {
  const deck = morph(slides)
  return deck
}`,
        `function talk() {
  const deck = morph(slides)
  return present(deck, {
    reactions: true,
  })
}`,
    ];
</script>

<div class="h-full w-full" data-test="landing-deck">
    <Presentation
        options={{
            hash: false,
            embedded: true,
            controls: true,
            progress: true,
            loop: true,
            keyboardCondition: 'focused',
            autoSlide: reducedMotion ? 0 : 5000,
            autoSlideStoppable: true,
        }}
    >
        <Slide background="#faf7f0" class="h-full w-full">
            <div
                class="flex h-full w-full flex-col items-start justify-center gap-[3cqh] px-[8cqw] text-left"
            >
                <h1
                    class="font-display text-[9cqw] leading-none font-bold tracking-tight text-[#221c12]"
                >
                    Tecturn<span class="text-[#d98a12]">.</span>
                </h1>
                <p class="text-[3.2cqw] text-[#6e6250]">
                    The stage for developer talks.
                </p>
                <Transition order={1}>
                    <p class="font-mono text-[1.7cqw] text-[#a5947a]">
                        (this hero is a live deck — press →)
                    </p>
                </Transition>
            </div>
        </Slide>

        <Slide background="#faf7f0" class="h-full w-full">
            <div
                class="flex h-full w-full flex-col items-start justify-center gap-[4cqh] px-[8cqw] text-left"
            >
                <h2
                    class="font-display text-[4cqw] font-semibold tracking-tight text-[#221c12]"
                >
                    Slides that morph like code.
                </h2>
                <div
                    class="w-[56cqw] [&_pre]:m-0 [&_pre]:overflow-auto [&_pre]:rounded-lg [&_pre]:bg-[#24292e] [&_pre]:p-[1.6cqw] [&_pre]:text-[1.8cqw]! [&_pre]:leading-relaxed"
                >
                    <Code
                        codes={morphPages}
                        lang="js"
                        theme="github-dark"
                        autoIndent={false}
                    />
                </div>
                <p class="font-mono text-[1.6cqw] text-[#a5947a]">
                    each press of → rewrites the block in place
                </p>
            </div>
        </Slide>

        <Slide background="#faf7f0" class="h-full w-full">
            <div
                class="flex h-full w-full flex-col items-start justify-center gap-[2.5cqh] px-[8cqw] text-left"
            >
                <p
                    class="font-display text-[4.6cqw] leading-tight font-semibold tracking-tight text-[#221c12]"
                >
                    Speak in your language.
                </p>
                <Transition order={1}>
                    <p
                        class="font-display text-[4.6cqw] leading-tight font-semibold tracking-tight text-[#b06e10]"
                    >
                        They read it in theirs.
                    </p>
                </Transition>
                <Transition order={2}>
                    <p class="pt-[2cqh] text-[2cqw] text-[#6e6250]">
                        Live translation and floating audience reactions, built
                        in.
                    </p>
                </Transition>
            </div>
        </Slide>

        <Slide background="#191510" class="h-full w-full">
            <div
                class="flex h-full w-full flex-col items-start justify-center gap-[3cqh] px-[8cqw] text-left"
            >
                <p
                    class="font-display text-[6.5cqw] leading-none font-bold tracking-tight text-[#f6efe1]"
                >
                    Take the stage<span class="text-[#f0a223]">.</span>
                </p>
                <p class="text-[2.2cqw] text-[#a5947a]">
                    Sign in and start your first deck.
                </p>
            </div>
        </Slide>
    </Presentation>
</div>

<style>
    /* Reveal chrome reads oversized on an embedded hero stage: shrink the
       nav arrows (em-driven) and quiet the autoplay pause button. */
    [data-test='landing-deck'] :global(.reveal .controls) {
        font-size: 7px;
    }

    [data-test='landing-deck'] :global(.reveal .playback) {
        transform: scale(0.7);
        transform-origin: bottom left;
        opacity: 0.5;
    }

    @media (max-width: 640px) {
        [data-test='landing-deck'] :global(.reveal .playback) {
            display: none;
        }
    }
</style>
