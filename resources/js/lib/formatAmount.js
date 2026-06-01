/**
 * Strip grouping commas and keep a valid decimal amount (max 2 decimal places).
 */
export function parseAmountInput(value) {
    const cleaned = String(value ?? '').replace(/,/g, '');

    if (cleaned === '') {
        return '';
    }

    const match = cleaned.match(/^\d*\.?\d{0,2}/);

    return match ? match[0] : '';
}

/**
 * Format a numeric amount string with thousands separators.
 */
export function formatAmountDisplay(value) {
    const raw = parseAmountInput(value);

    if (raw === '') {
        return '';
    }

    const [whole, fraction] = raw.split('.');
    const withCommas = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    return fraction !== undefined ? `${withCommas}.${fraction}` : withCommas;
}
