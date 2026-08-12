<template>
    <div class="group relative overflow-hidden rounded-[28px] border border-[#6FBF8A]/25 bg-[#F4F6F1]/[0.98] p-5 shadow-[0_20px_80px_rgba(0,0,0,0.3)] backdrop-blur-xl md:p-7">
        <div class="pointer-events-none absolute inset-0 rounded-[28px] border border-white/10"></div>
        <div class="pointer-events-none absolute inset-x-0 top-0 h-20 bg-gradient-to-b from-white/70 to-transparent"></div>
        <div class="pointer-events-none absolute -inset-px rounded-[28px] opacity-80 [background:radial-gradient(circle_at_top_right,rgba(111,191,138,0.16),transparent_34%),radial-gradient(circle_at_bottom_left,rgba(47,107,50,0.1),transparent_32%)]"></div>
        <div class="pointer-events-none absolute inset-x-6 top-4 flex gap-1.5">
            <span class="h-2.5 w-2.5 rounded-full bg-[#123D2A]"></span>
            <span class="h-2.5 w-2.5 rounded-full bg-[#2F6B32]"></span>
            <span class="h-2.5 w-2.5 rounded-full bg-[#6FBF8A]"></span>
        </div>
        <div class="relative z-10 pt-6">
            <slot />
        </div>
    </div>
</template>
