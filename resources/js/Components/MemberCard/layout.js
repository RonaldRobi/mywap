/**
 * Susun atur kad keahlian (saiz kad TNG 54 × 85.6 mm, menegak).
 *
 * Semua koordinat dalam piksel kanvas design 638 × 1004 (300 DPI).
 * Design depan/belakang di-upload oleh superadmin; app hanya isi medan
 * dinamik di bawah. Guna nombor yang SAMA untuk Flutter supaya web & app
 * kekal sebiji.
 */
export const CARD_W = 638;
export const CARD_H = 1004;

export const FONT_FAMILY = 'Figtree, ui-sans-serif, system-ui, sans-serif';

export const CARD_LAYOUT = {
    photo: { cx: 320, cy: 428, r: 130 },

    // Nama: boleh 1 atau 2 baris. Kotak organisasi dijarakkan 50px dari
    // baris TERAKHIR nama, jadi nama 1 baris tidak simpan ruang kosong.
    name: {
        centerX: 319,
        maxWidth: 520,
        line1: 600,
        lineHeight: 36,
        fontSize: 30,
        minFontSize: 20,
        fontWeight: 700,
        color: '#00291a',
    },

    // Kotak organisasi + teks putih di tengah.
    organization: {
        box: { x: 185, w: 272, h: 40, radius: 5, color: '#03553e' },
        paddingX: 14,
        baselineOffset: 27,
        fontSize: 28,
        minFontSize: 12,
        fontWeight: 800,
        color: '#ffffff',
    },

    memberNo: {
        centerX: 319,
        fontSize: 20,
        minFontSize: 12,
        maxWidth: 400,
        fontWeight: 700,
        color: '#012c10',
    },

    gaps: {
        nameToOrg: 50,
        orgToMemberNo: 50,
    },

    qr: { x: 480, y: 845, size: 95, padding: 3, background: '#ffffff' },
};

let measureCtx = null;

function ctx() {
    if (!measureCtx && typeof document !== 'undefined') {
        measureCtx = document.createElement('canvas').getContext('2d');
    }
    return measureCtx;
}

export function fontString(size, weight) {
    return `${weight} ${size}px ${FONT_FAMILY}`;
}

export function measure(text, size, weight) {
    const c = ctx();
    if (!c) return text.length * size * 0.6;
    c.font = fontString(size, weight);
    return c.measureText(text).width;
}

/** Saiz font terbesar (≤ fontSize) yang muat satu baris dalam maxWidth. */
export function fitSingleLine(text, { fontSize, minFontSize, fontWeight }, maxWidth) {
    let size = fontSize;
    while (size > minFontSize && measure(text, size, fontWeight) > maxWidth) {
        size -= 1;
    }
    return size;
}

function wrap(words, size, weight, maxWidth) {
    const lines = [];
    let current = '';
    for (const word of words) {
        const candidate = current ? `${current} ${word}` : word;
        if (!current || measure(candidate, size, weight) <= maxWidth) {
            current = candidate;
        } else {
            lines.push(current);
            current = word;
        }
    }
    if (current) lines.push(current);
    return lines;
}

/**
 * Pecahkan nama kepada ≤ 2 baris dan kecilkan font sehingga muat.
 * Pulangkan { size, lines: [{ text, baseline }], bottom }.
 */
export function layoutName(rawName) {
    const cfg = CARD_LAYOUT.name;
    const name = (rawName || '').trim().toUpperCase() || '-';
    const words = name.split(/\s+/);

    let size = cfg.fontSize;
    let lines = wrap(words, size, cfg.fontWeight, cfg.maxWidth);

    while (size > cfg.minFontSize && (lines.length > 2 || lines.some(l => measure(l, size, cfg.fontWeight) > cfg.maxWidth))) {
        size -= 1;
        lines = wrap(words, size, cfg.fontWeight, cfg.maxWidth);
    }

    if (lines.length > 2) {
        // Masih panjang pada saiz minimum: gabungkan baki ke baris kedua.
        lines = [lines[0], lines.slice(1).join(' ')];
    }

    const baselines = lines.length === 1
        ? [cfg.line1]
        : [cfg.line1, cfg.line1 + cfg.lineHeight];
    const lastBaseline = baselines[baselines.length - 1];
    const bottom = lastBaseline + size * 0.22;

    return {
        size,
        lines: lines.map((text, i) => ({ text, baseline: baselines[i] })),
        bottom,
    };
}

export function layoutOrganization(rawName, nameBottom) {
    const cfg = CARD_LAYOUT.organization;
    const text = (rawName || '').trim().toUpperCase() || 'ORGANISASI';
    const size = fitSingleLine(text, cfg, cfg.box.w - cfg.paddingX * 2);
    const boxTop = Math.round(nameBottom + CARD_LAYOUT.gaps.nameToOrg);

    return {
        text,
        size,
        boxTop,
        baseline: boxTop + cfg.baselineOffset,
    };
}

export function layoutMemberNo(raw, orgBoxTop) {
    const cfg = CARD_LAYOUT.memberNo;
    const text = (raw || '').trim() || '-';
    const size = fitSingleLine(text, cfg, cfg.maxWidth);
    const memberNoTop = orgBoxTop + CARD_LAYOUT.organization.box.h + CARD_LAYOUT.gaps.orgToMemberNo;

    return {
        text,
        size,
        baseline: memberNoTop + size * 0.72,
    };
}

export function initials(name) {
    return (name || 'U')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map(v => v[0])
        .join('')
        .toUpperCase();
}
