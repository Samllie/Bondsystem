export function buildReportFilterSummary(parts) {
    return parts.filter(Boolean);
}

export function branchFilterSummary(filters, branchOptions) {
    if (!filters.branch_id || !branchOptions?.length) {
        return null;
    }

    const branch = branchOptions.find((option) => String(option.value) === String(filters.branch_id));

    return `Branch: ${branch?.label ?? filters.branch_id}`;
}

export function bondRequestFilterSummary(filters, statusOptions, bondTypeOptions, branchOptions) {
    const summary = [];

    if (filters.search) {
        summary.push(`Search: ${filters.search}`);
    }

    if (filters.status) {
        const status = statusOptions.find((option) => option.value === filters.status);
        summary.push(`Status: ${status?.label ?? filters.status}`);
    }

    if (filters.bond_type_id) {
        const bondType = bondTypeOptions.find((option) => String(option.value) === String(filters.bond_type_id));
        summary.push(`Bond Type: ${bondType?.label ?? filters.bond_type_id}`);
    }

    const branch = branchFilterSummary(filters, branchOptions);
    if (branch) {
        summary.push(branch);
    }

    return summary;
}

export function transactionFilterSummary(filters, branchOptions) {
    const summary = [];

    if (filters.search) {
        summary.push(`Search: ${filters.search}`);
    }

    if (filters.type) {
        summary.push(`Type: ${filters.type === 'credit' ? 'Credit' : 'Debit'}`);
    }

    const branch = branchFilterSummary(filters, branchOptions);
    if (branch) {
        summary.push(branch);
    }

    return summary;
}

export function paymentHistoryFilterSummary(filters, branchOptions) {
    const summary = filters.search ? [`Search: ${filters.search}`] : [];

    const branch = branchFilterSummary(filters, branchOptions);
    if (branch) {
        summary.push(branch);
    }

    return summary;
}

export function certificationFilterSummary(filters, branchOptions) {
    const summary = [];

    if (filters.search) {
        summary.push(`Search: ${filters.search}`);
    }

    const branch = branchFilterSummary(filters, branchOptions);
    if (branch) {
        summary.push(branch);
    }

    return summary;
}

export function depositFilterSummary(filters, statusOptions, branchOptions) {
    const summary = [];

    if (filters.search) {
        summary.push(`Search: ${filters.search}`);
    }

    if (filters.status) {
        const status = statusOptions.find((option) => option.value === filters.status);
        summary.push(`Status: ${status?.label ?? filters.status}`);
    }

    const branch = branchFilterSummary(filters, branchOptions);
    if (branch) {
        summary.push(branch);
    }

    return summary;
}

export function auditLogFilterSummary(filters, userOptions, actionOptions, entityTypeOptions) {
    const summary = [];

    if (filters.user_id) {
        const user = userOptions.find((option) => String(option.value) === String(filters.user_id));
        summary.push(`User: ${user?.label ?? filters.user_id}`);
    }

    if (filters.action) {
        const action = actionOptions.find((option) => option.value === filters.action);
        summary.push(`Action: ${action?.label ?? filters.action}`);
    }

    if (filters.entity_type) {
        const entityType = entityTypeOptions.find((option) => option.value === filters.entity_type);
        summary.push(`Entity Type: ${entityType?.label ?? filters.entity_type}`);
    }

    if (filters.date_from) {
        summary.push(`Date From: ${filters.date_from}`);
    }

    if (filters.date_to) {
        summary.push(`Date To: ${filters.date_to}`);
    }

    return summary;
}
