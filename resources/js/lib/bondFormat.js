export function buildBondValue(bondTypeLabel, branchCode, bondNumber, serial) {
    if (!bondTypeLabel || !bondNumber || !branchCode || !serial) {
        return '';
    }

    return `${bondTypeLabel} NO. ${bondNumber}-${String(branchCode).toUpperCase()}-${serial}`;
}

export function buildCarValue(branchCode, serial = '0072056') {
    const code = String(branchCode || '').toUpperCase();
    const digits = String(serial || '').replace(/\D/g, '').padStart(7, '0').slice(-7);

    if (!code) {
        return '';
    }

    return `CAR-${code}-${digits}`;
}
