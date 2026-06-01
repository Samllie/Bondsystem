const ones = [
    '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
    'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
    'Seventeen', 'Eighteen', 'Nineteen',
];

const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

const scales = [
    [1_000_000_000_000_000, 'Quadrillion'],
    [1_000_000_000_000, 'Trillion'],
    [1_000_000_000, 'Billion'],
    [1_000_000, 'Million'],
    [1_000, 'Thousand'],
];

function convertHundreds(number) {
    const words = [];

    if (number >= 100) {
        words.push(`${ones[Math.floor(number / 100)]} Hundred`);
        number %= 100;
    }

    if (number >= 20) {
        words.push(tens[Math.floor(number / 10)]);

        if (number % 10 > 0) {
            words.push(ones[number % 10]);
        }
    } else if (number > 0) {
        words.push(ones[number]);
    }

    return words.join(' ').trim();
}

function convertChunk(value) {
    return value > 999n ? convertNumberFromBigInt(value) : convertHundreds(Number(value));
}

function convertNumberFromBigInt(value) {
    if (value === 0n) {
        return 'Zero';
    }

    const parts = [];
    let remainder = value;

    for (const [scale, name] of scales) {
        const scaleBig = BigInt(scale);

        if (remainder < scaleBig) {
            continue;
        }

        const chunk = remainder / scaleBig;
        parts.push(`${convertChunk(chunk)} ${name}`);
        remainder %= scaleBig;
    }

    if (remainder > 0n) {
        parts.push(convertChunk(remainder));
    }

    return parts.join(' ');
}

function parseAmountParts(amount) {
    const cleaned = String(amount ?? '').replace(/,/g, '').trim();

    if (cleaned === '' || cleaned === '-') {
        return null;
    }

    const negative = cleaned.startsWith('-');
    const raw = negative ? cleaned.slice(1) : cleaned;

    if (!/^\d+(\.\d{1,2})?$/.test(raw)) {
        return null;
    }

    const [whole, fraction = '0'] = raw.split('.');
    const pesos = BigInt(whole || '0');
    const centavos = parseInt(fraction.padEnd(2, '0').slice(0, 2), 10);

    return { pesos, centavos, negative };
}

export function amountInWords(amount) {
    const parsed = parseAmountParts(amount);

    if (parsed === null || parsed.negative) {
        return parsed?.negative ? '' : '';
    }

    const { pesos, centavos } = parsed;

    let words = `${convertNumberFromBigInt(pesos)} Peso${pesos === 1n ? '' : 's'}`;

    if (centavos > 0) {
        words += ` and ${convertNumberFromBigInt(BigInt(centavos))} Centavo${centavos === 1 ? '' : 's'}`;
    }

    return `${words} Only`;
}
