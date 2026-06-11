<template>
    <div class="group relative overflow-hidden rounded-[28px] border border-white/15 bg-slate-950/55 p-5 shadow-[0_20px_80px_rgba(0,0,0,0.45)] backdrop-blur-xl md:p-7">
        <div class="pointer-events-none absolute inset-0 rounded-[28px] border border-white/10"></div>
        <div class="pointer-events-none absolute inset-x-0 top-0 h-20 bg-gradient-to-b from-white/10 to-transparent"></div>
        <div class="pointer-events-none absolute -inset-px rounded-[28px] opacity-80 [background:radial-gradient(circle_at_top_right,rgba(45,212,191,0.18),transparent_34%),radial-gradient(circle_at_bottom_left,rgba(129,140,248,0.16),transparent_32%)]"></div>
        <div class="pointer-events-none absolute inset-x-6 top-4 flex gap-1.5">
            <span class="h-2.5 w-2.5 rounded-full bg-rose-300/80"></span>
            <span class="h-2.5 w-2.5 rounded-full bg-amber-300/80"></span>
            <span class="h-2.5 w-2.5 rounded-full bg-emerald-300/80"></span>
        </div>
        <div class="relative z-10">
            <slot />
        </div>
    </div>
</template>
