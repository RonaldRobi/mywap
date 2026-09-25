/**
 * Shared Malaysian IC → DOB helper.
 *
 * The two-digit birth year is a sliding 100-year window ending at the current
 * year: "00" is 2000 (not 1900) and, in 2026, "26" is 2026 while "27" is 1927.
 * A hardcoded cutoff (e.g. `yy > 25`) goes stale every January and misparses
 * the newest birth years, so derive it from the current date instead.
 */
export function parseDobFromIc(ic) {
    if (!ic) return '';
    const digits = String(ic).replace(/[^0-9]/g, '');
    if (digits.length < 6) return '';

    const yy = parseInt(digits.substring(0, 2), 10);
    const mm = parseInt(digits.substring(2, 4), 10);
    const dd = parseInt(digits.substring(4, 6), 10);

    if (mm < 1 || mm > 12 || dd < 1 || dd > 31) return '';

    let yyyy = 2000 + yy;
    if (yyyy > new Date().getFullYear()) yyyy -= 100;

    return `${yyyy}-${String(mm).padStart(2, '0')}-${String(dd).padStart(2, '0')}`;
}
