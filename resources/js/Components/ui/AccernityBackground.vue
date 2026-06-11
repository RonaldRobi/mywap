<template>
    <div class="relative min-h-screen overflow-hidden bg-slate-950">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(16,185,129,0.14),transparent_30%),radial-gradient(circle_at_80%_20%,rgba(99,102,241,0.16),transparent_28%),radial-gradient(circle_at_50%_100%,rgba(34,211,238,0.14),transparent_30%)]"></div>

            <div class="spotlight spotlight-left"></div>
            <div class="spotlight spotlight-center"></div>
            <div class="spotlight spotlight-right"></div>

            <div class="beam beam-1"></div>
            <div class="beam beam-2"></div>
            <div class="beam beam-3"></div>

            <div class="absolute -top-24 -left-16 h-80 w-80 rounded-full bg-emerald-500/25 blur-3xl animate-float-slow"></div>
            <div class="absolute top-1/4 -right-24 h-96 w-96 rounded-full bg-indigo-500/25 blur-3xl animate-float-medium"></div>
            <div class="absolute bottom-0 left-1/3 h-72 w-72 rounded-full bg-cyan-500/20 blur-3xl animate-float-slow"></div>

            <div class="absolute inset-0 opacity-[0.08] [background-image:linear-gradient(to_right,#fff_1px,transparent_1px),linear-gradient(to_bottom,#fff_1px,transparent_1px)] [background-size:48px_48px]"></div>
            <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/40 to-transparent"></div>
        </div>

        <div class="relative z-10">
            <slot />
        </div>
    </div>
</template>

<style scoped>
.spotlight {
    position: absolute;
    top: -12rem;
    width: 28rem;
    height: 28rem;
    border-radius: 9999px;
    filter: blur(80px);
    opacity: 0.22;
    transform: translateX(-50%);
}

.spotlight-left {
    left: 18%;
    background: radial-gradient(circle, rgba(16,185,129,0.55) 0%, rgba(16,185,129,0) 68%);
}

.spotlight-center {
    left: 50%;
    background: radial-gradient(circle, rgba(255,255,255,0.22) 0%, rgba(255,255,255,0) 68%);
}

.spotlight-right {
    left: 82%;
    background: radial-gradient(circle, rgba(99,102,241,0.55) 0%, rgba(99,102,241,0) 68%);
}

.beam {
    position: absolute;
    top: -10%;
    width: 1px;
    height: 120%;
    background: linear-gradient(to bottom, transparent, rgba(255,255,255,0.28), transparent);
    opacity: 0.5;
    animation: beamFloat 10s linear infinite;
}

.beam-1 { left: 18%; animation-delay: 0s; }
.beam-2 { left: 52%; animation-delay: 2s; }
.beam-3 { left: 78%; animation-delay: 4s; }

.animate-float-slow {
    animation: floatSlow 11s ease-in-out infinite;
}

.animate-float-medium {
    animation: floatMedium 8s ease-in-out infinite;
}

@keyframes beamFloat {
    0% { transform: translateY(-6%) scaleY(0.95); opacity: 0.15; }
    50% { transform: translateY(2%) scaleY(1); opacity: 0.55; }
    100% { transform: translateY(-6%) scaleY(0.95); opacity: 0.15; }
}

@keyframes floatSlow {
    0%, 100% { transform: translate3d(0, 0, 0); }
    50% { transform: translate3d(0, 18px, 0); }
}

@keyframes floatMedium {
    0%, 100% { transform: translate3d(0, 0, 0); }
    50% { transform: translate3d(-12px, 12px, 0); }
}
</style>
