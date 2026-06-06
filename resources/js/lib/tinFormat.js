export const TIN_SUFFIX = '0000';

export function parseTinParts(tin) {
    const match = String(tin || '').match(/^(\d{0,3})-(\d{0,3})-(\d{0,3})-0000$/);

    if (match) {
        return [match[1], match[2], match[3]];
    }

    const digits = String(tin || '').replace(/\D/g, '').slice(0, 9);

    return [digits.slice(0, 3), digits.slice(3, 6), digits.slice(6, 9)];
}

export function buildTin(part1, part2, part3) {
    const segments = [part1, part2, part3].map((part) => String(part || '').replace(/\D/g, '').slice(0, 3));

    if (segments.every((segment) => segment.length === 0)) {
        return '';
    }

    return `${segments[0]}-${segments[1]}-${segments[2]}-${TIN_SUFFIX}`;
}

export function isCompleteTin(tin) {
    return /^\d{3}-\d{3}-\d{3}-0000$/.test(String(tin || ''));
}
