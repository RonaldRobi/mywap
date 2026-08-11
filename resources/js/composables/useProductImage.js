/**
 * Shared helpers for resolving and formatting product media/prices.
 *
 * Previously each mall page carried its own copy of `productImageUrl`, which
 * meant a fix in one place silently missed the others. Keep it here.
 */

const PLACEHOLDER = '/images/product-placeholder.svg';

/**
 * Turn a stored image path into a usable URL.
 *
 * Accepts absolute URLs, already-prefixed `/storage/...` paths, and bare
 * relative paths such as `products/abc.jpg`.
 */
export function productImageUrl(path) {
    if (!path) return PLACEHOLDER;
    if (/^(https?:)?\/\//.test(path)) return path;
    if (path.startsWith('data:') || path.startsWith('blob:')) return path;
    if (path.startsWith('/storage/')) return path;
    return '/storage/' + String(path).replace(/^\/+/, '');
}

/**
 * Full gallery for a product: main image first, then extras, de-duplicated.
 */
export function productGallery(product) {
    const raw = [product?.image, ...(product?.images ?? [])].filter(Boolean);
    const unique = [...new Set(raw)];
    return unique.length ? unique.map(productImageUrl) : [PLACEHOLDER];
}

/** Swap in the placeholder when an image 404s. */
export function onImageError(event) {
    if (event?.target && event.target.src !== window.location.origin + PLACEHOLDER) {
        event.target.src = PLACEHOLDER;
    }
}

/** Format a number as Malaysian Ringgit. */
export function formatPrice(value) {
    return new Intl.NumberFormat('ms-MY', {
        style: 'currency',
        currency: 'MYR',
        minimumFractionDigits: 2,
    }).format(Number(value ?? 0));
}

/** Percentage saved when a member price undercuts the list price. */
export function discountPercent(price, memberPrice) {
    const p = Number(price ?? 0);
    const m = Number(memberPrice ?? 0);
    if (!p || !memberPrice || m >= p) return 0;
    return Math.round(((p - m) / p) * 100);
}

export { PLACEHOLDER };
