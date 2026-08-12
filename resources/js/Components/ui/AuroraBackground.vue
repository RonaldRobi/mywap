<template>
    <div class="relative min-h-screen overflow-hidden bg-[#071525]">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,rgba(111,191,138,0.16),transparent_42%),radial-gradient(ellipse_at_left,rgba(47,107,50,0.2),transparent_36%),radial-gradient(ellipse_at_right,rgba(18,61,42,0.3),transparent_34%)]"></div>
            <div class="aurora aurora-a"></div>
            <div class="aurora aurora-b"></div>
            <div class="aurora aurora-c"></div>
            <div class="absolute inset-0 opacity-[0.07] [background-image:linear-gradient(to_right,#fff_1px,transparent_1px),linear-gradient(to_bottom,#fff_1px,transparent_1px)] [background-size:52px_52px]"></div>
            <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/30 to-transparent"></div>
            <div class="absolute bottom-0 left-0 right-0 h-40 bg-gradient-to-t from-[#071525] via-[#071525]/80 to-transparent"></div>
        </div>

        <div class="relative z-10">
            <slot />
        </div>
    </div>
</template>

<style scoped>
.aurora {
    position: absolute;
    inset: auto;
    width: 46rem;
    height: 46rem;
    border-radius: 9999px;
    filter: blur(90px);
    mix-blend-mode: screen;
    opacity: 0.28;
    animation: auroraFloat 14s ease-in-out infinite alternate;
}

.aurora-a {
    top: -12rem;
    left: -8rem;
    background: conic-gradient(from 180deg, rgba(111,191,138,0.55), rgba(18,61,42,0.1), rgba(47,107,50,0.5), rgba(111,191,138,0.55));
}

.aurora-b {
    top: -10rem;
    right: -10rem;
    background: conic-gradient(from 120deg, rgba(47,107,50,0.65), rgba(111,191,138,0.14), rgba(18,61,42,0.62), rgba(47,107,50,0.65));
    animation-duration: 18s;
}

.aurora-c {
    bottom: -18rem;
    left: 25%;
    width: 38rem;
    height: 38rem;
    background: conic-gradient(from 240deg, rgba(111,191,138,0.5), rgba(18,61,42,0.15), rgba(47,107,50,0.45), rgba(111,191,138,0.5));
    animation-duration: 16s;
}

@keyframes auroraFloat {
    0% {
        transform: translate3d(0, 0, 0) scale(1) rotate(0deg);
    }
    50% {
        transform: translate3d(2rem, 1rem, 0) scale(1.05) rotate(8deg);
    }
    100% {
        transform: translate3d(-1rem, -1.5rem, 0) scale(0.98) rotate(-6deg);
    }
}
</style>
