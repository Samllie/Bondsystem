const ROMAN_MAP = [
    [1000, 'M'],
    [900, 'CM'],
    [500, 'D'],
    [400, 'CD'],
    [100, 'C'],
    [90, 'XC'],
    [50, 'L'],
    [40, 'XL'],
    [10, 'X'],
    [9, 'IX'],
    [5, 'V'],
    [4, 'IV'],
    [1, 'I'],
];

const ROMAN_PATTERN = /^M{0,3}(CM|CD|D?C{0,3})(XC|XL|L?X{0,3})(IX|IV|V?I{0,3})$/i;

export function toRomanNumeral(value) {
    const number = Number.parseInt(String(value), 10);

    if (!Number.isFinite(number) || number <= 0 || number > 3999) {
        return '';
    }

    let remaining = number;
    let result = '';

    for (const [amount, numeral] of ROMAN_MAP) {
        while (remaining >= amount) {
            result += numeral;
            remaining -= amount;
        }
    }

    return result;
}

export function isValidRomanNumeral(value) {
    const normalized = String(value).trim().toUpperCase();

    if (normalized === '') {
        return true;
    }

    return ROMAN_PATTERN.test(normalized);
}

export function formatBookNoInput(value) {
    const trimmed = String(value).trim();

    if (trimmed === '') {
        return '';
    }

    if (/^\d+$/.test(trimmed)) {
        return toRomanNumeral(trimmed) || trimmed;
    }

    return trimmed.toUpperCase().replace(/[^IVXLCDM]/g, '');
}

export function formatBookNoDisplay(value) {
    const trimmed = String(value ?? '').trim();

    if (trimmed === '') {
        return '';
    }

    if (/^\d+$/.test(trimmed)) {
        return toRomanNumeral(trimmed) || trimmed;
    }

    if (isValidRomanNumeral(trimmed)) {
        return trimmed.toUpperCase();
    }

    return trimmed;
}
