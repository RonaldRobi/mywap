<script setup>
import { computed } from 'vue';
import {
    CARD_H,
    CARD_LAYOUT as L,
    CARD_W,
    FONT_FAMILY,
    initials,
    layoutMemberNo,
    layoutName,
    layoutOrganization,
} from './layout';

const props = defineProps({
    card: { type: Object, required: true },
    // SVG QR mentah dari server (MemberCoreService::generateQrSvg).
    qrSvg: { type: String, default: null },
    // Dinaikkan bila font web siap dimuat supaya ukuran teks dikira semula.
    fontsVersion: { type: Number, default: 0 },
});

const emit = defineEmits(['qr-click']);

const uid = `mc${Math.random().toString(36).slice(2, 8)}`;

const name = computed(() => (props.fontsVersion, layoutName(props.card?.name)));
const org = computed(() => (props.fontsVersion, layoutOrganization(props.card?.organization?.name, name.value.bottom)));
const memberNo = computed(() => (props.fontsVersion, layoutMemberNo(props.card?.member_no, org.value.boxTop)));

const badge = computed(() => {
    const raw = props.card?.position_badge;
    if (!raw || !raw.name || !String(raw.name).trim()) return null;

    const text = String(raw.name).trim();
    const fontSize = text.length > 22 ? 14 : 18;
    const width = Math.min(380, Math.max(130, text.length * fontSize * 0.56 + 36));
    const height = 30;
    const top = memberNo.value.baseline + 8;
    const color = /^#[0-9a-f]{6}$/i.test(raw.color || '') ? raw.color : '#147a3d';

    return {
        text,
        fontSize,
        width,
        height,
        top,
        left: L.name.centerX - width / 2,
        color,
        textX: L.name.centerX - width / 2 + 24,
        textY: top + height / 2 + fontSize * 0.35,
    };
});

const qrDataUri = computed(() => {
    if (!props.qrSvg) return null;
    const svg = props.qrSvg.includes('xmlns=')
        ? props.qrSvg
        : props.qrSvg.replace('<svg', '<svg xmlns="http://www.w3.org/2000/svg"');
    return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
});

const qrInner = computed(() => ({
    x: L.qr.x + L.qr.padding,
    y: L.qr.y + L.qr.padding,
    size: L.qr.size - L.qr.padding * 2,
}));
</script>

<template>
    <svg
        :viewBox="`0 0 ${CARD_W} ${CARD_H}`"
        xmlns="http://www.w3.org/2000/svg"
        class="block h-full w-full select-none"
        role="img"
        :aria-label="`Kad keahlian ${card?.name || ''}`"
    >
        <defs>
            <clipPath :id="`${uid}-photo`">
                <circle :cx="L.photo.cx" :cy="L.photo.cy" :r="L.photo.r" />
            </clipPath>
            <linearGradient :id="`${uid}-fallback`" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#1a6b4f" />
                <stop offset="100%" stop-color="#0f5a3e" />
            </linearGradient>
        </defs>

        <!-- Background design (per organisasi) -->
        <image
            v-if="card?.card_front_path"
            :href="card.card_front_path"
            x="0" y="0" :width="CARD_W" :height="CARD_H"
            preserveAspectRatio="xMidYMid slice"
        />
        <rect v-else x="0" y="0" :width="CARD_W" :height="CARD_H" :fill="`url(#${uid}-fallback)`" />

        <!-- Foto ahli (bulat) -->
        <g :clip-path="`url(#${uid}-photo)`">
            <circle :cx="L.photo.cx" :cy="L.photo.cy" :r="L.photo.r" fill="#dfeee5" />
            <image
                v-if="card?.photo_url"
                :href="card.photo_url"
                :x="L.photo.cx - L.photo.r" :y="L.photo.cy - L.photo.r"
                :width="L.photo.r * 2" :height="L.photo.r * 2"
                preserveAspectRatio="xMidYMid slice"
            />
            <text
                v-else
                :x="L.photo.cx"
                :y="L.photo.cy + 24"
                text-anchor="middle"
                :font-family="FONT_FAMILY"
                font-size="72"
                font-weight="800"
                fill="#03553e"
            >{{ initials(card?.name) }}</text>
        </g>

        <!-- Nama -->
        <text
            v-for="(line, i) in name.lines"
            :key="`n${i}`"
            :x="L.name.centerX"
            :y="line.baseline"
            text-anchor="middle"
            :font-family="FONT_FAMILY"
            :font-size="name.size"
            :font-weight="L.name.fontWeight"
            :fill="L.name.color"
        >{{ line.text }}</text>

        <!-- Kotak organisasi -->
        <rect
            :x="L.organization.box.x" :y="org.boxTop"
            :width="L.organization.box.w" :height="L.organization.box.h"
            :rx="L.organization.box.radius"
            :fill="L.organization.box.color"
        />
        <text
            :x="L.organization.box.x + L.organization.box.w / 2"
            :y="org.baseline"
            text-anchor="middle"
            :font-family="FONT_FAMILY"
            :font-size="org.size"
            :font-weight="L.organization.fontWeight"
            :fill="L.organization.color"
            letter-spacing="1"
        >{{ org.text }}</text>

        <!-- No. ahli -->
        <text
            :x="L.memberNo.centerX"
            :y="memberNo.baseline"
            text-anchor="middle"
            :font-family="FONT_FAMILY"
            :font-size="memberNo.size"
            :font-weight="L.memberNo.fontWeight"
            :fill="L.memberNo.color"
            letter-spacing="1"
        >{{ memberNo.text }}</text>

        <!-- Badge jawatan (jika ada) -->
        <g v-if="badge">
            <rect
                :x="badge.left" :y="badge.top"
                :width="badge.width" :height="badge.height"
                rx="8" ry="8"
                fill="rgba(255,255,255,0.94)"
                stroke="#d7e3da"
                stroke-width="1"
            />
            <rect
                :x="badge.left + 12" :y="badge.top + 8"
                width="3" :height="badge.height - 16"
                rx="1.5" ry="1.5"
                :fill="badge.color"
            />
            <text
                :x="badge.textX"
                :y="badge.textY"
                text-anchor="start"
                :font-family="FONT_FAMILY"
                :font-size="badge.fontSize"
                font-weight="700"
                fill="#00291a"
            >{{ badge.text }}</text>
        </g>

        <!-- QR (tekan untuk besarkan) -->
        <g class="cursor-pointer" @click.stop="emit('qr-click')">
            <rect :x="L.qr.x" :y="L.qr.y" :width="L.qr.size" :height="L.qr.size" rx="3" :fill="L.qr.background" />
            <image
                v-if="qrDataUri"
                :href="qrDataUri"
                :x="qrInner.x" :y="qrInner.y" :width="qrInner.size" :height="qrInner.size"
            />
        </g>
    </svg>
</template>
