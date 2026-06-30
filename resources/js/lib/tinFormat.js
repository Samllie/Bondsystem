export const TIN_SUFFIX = '0000';

export function parseTinParts(tin) {
    const match = String(tin || '').match(/^(\d{0,3})-(\d{0,3})-(\d{0,3})-(\d{0,4})$/);

    if (match) {
        return [match[1], match[2], match[3], match[4]];
    }

    const digits = String(tin || '').replace(/\D/g, '').slice(0, 12);

    return [
        digits.slice(0, 3),
        digits.slice(3, 6),
        digits.slice(6, 9),
        digits.slice(9, 12),
    ];
}

export function buildTin(part1, part2, part3, part4) {
    const lengths = [3, 3, 3, 4];
    const segments = [part1, part2, part3, part4].map((part, index) =>
        String(part || '')
            .replace(/\D/g, '')
            .slice(0, lengths[index]),
    );

    if (segments.every((segment) => segment.length === 0)) {
        return '';
    }

    return `${segments[0]}-${segments[1]}-${segments[2]}-${segments[3]}`;
}

export function isCompleteTin(tin) {
    return /^\d{3}-\d{3}-\d{3}-\d{4}$/.test(String(tin || ''));
}
