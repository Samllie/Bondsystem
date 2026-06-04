export function buildBondValue(bondTypeLabel, branchCode, bondNumber, serial) {
    if (!bondTypeLabel || !bondNumber || !branchCode || !serial) {
        return '';
    }

    return `${bondTypeLabel} NO. ${bondNumber}-${String(branchCode).toUpperCase()}-${serial}`;
}
