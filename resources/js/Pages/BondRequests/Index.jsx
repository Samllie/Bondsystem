import PrimaryButton from '@/Components/PrimaryButton';
import FileDownloadLink from '@/Components/UI/FileDownloadLink';
import BranchFilter from '@/Components/Report/BranchFilter';
import PrintReportButton from '@/Components/Report/PrintReportButton';
import ReportPrintHeader from '@/Components/Report/ReportPrintHeader';
import Card, { CardBody } from '@/Components/UI/Card';
import Pagination from '@/Components/UI/Pagination';
import StatusBadge from '@/Components/UI/StatusBadge';
import TableSearchInput from '@/Components/UI/TableSearchInput';
import useDebouncedInertiaSearch from '@/hooks/useDebouncedInertiaSearch';
import { visitTable } from '@/lib/visitTable';
import { bondRequestFilterSummary } from '@/lib/reportPrint';
import { usePermission } from '@/hooks/usePermission';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const APPROVED_STATUSES = ['approved', 'notarized'];

function approverName(bond) {
    const statusValue = bond.status?.value || bond.status;

    if (!APPROVED_STATUSES.includes(statusValue)) {
        return '—';
    }

    return bond.approver?.name ?? '—';
}

const TABLE_PROPS = ['bondRequests', 'filters'];
const php = (v) => Number(v).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });

export default function Index({ bondRequests, filters, statusOptions, bondTypeOptions, branchOptions, showBranchFilter }) {
    const { can } = usePermission();
    const [status, setStatus] = useState(filters.status || '');
    const [bondTypeId, setBondTypeId] = useState(filters.bond_type_id || '');
    const [branchId, setBranchId] = useState(filters.branch_id || '');
    const url = route('bond-requests.index');
    const filterSummary = bondRequestFilterSummary(filters, statusOptions, bondTypeOptions, branchOptions);

    useEffect(() => {
        setStatus(filters.status || '');
        setBondTypeId(filters.bond_type_id || '');
        setBranchId(filters.branch_id || '');
    }, [filters.status, filters.bond_type_id, filters.branch_id]);

    const buildParams = (searchValue) => ({
        search: searchValue.trim() || undefined,
        status: status || undefined,
        bond_type_id: bondTypeId || undefined,
        branch_id: branchId || undefined,
    });

    const { inputRef, isSearching, onInput, getValue, defaultSearch } = useDebouncedInertiaSearch({
        initialSearch: filters.search,
        url,
        buildParams,
        only: TABLE_PROPS,
    });

    const handleStatusChange = (e) => {
        const value = e.target.value;
        setStatus(value);
        visitTable(url, {
            search: getValue().trim() || undefined,
            status: value || undefined,
            bond_type_id: bondTypeId || undefined,
            branch_id: branchId || undefined,
        }, TABLE_PROPS, { inputRef });
    };

    const handleBondTypeChange = (e) => {
        const value = e.target.value;
        setBondTypeId(value);
        visitTable(url, {
            search: getValue().trim() || undefined,
            status: status || undefined,
            bond_type_id: value || undefined,
            branch_id: branchId || undefined,
        }, TABLE_PROPS, { inputRef });
    };

    const handleBranchChange = (e) => {
        const value = e.target.value;
        setBranchId(value);
        visitTable(url, {
            search: getValue().trim() || undefined,
            status: status || undefined,
            bond_type_id: bondTypeId || undefined,
            branch_id: value || undefined,
        }, TABLE_PROPS, { inputRef });
    };

    return (
        <AppLayout
            title="Bond Requests"
            actions={
                <>
                    <PrintReportButton />
                    {can('bond-requests.create') && (
                        <Link href={route('bond-requests.create')}>
                            <PrimaryButton>New Bond Request</PrimaryButton>
                        </Link>
                    )}
                </>
            }
        >
            <Head title="Bond Requests" />

            <ReportPrintHeader title="Bond Requests Report" filterSummary={filterSummary} />

            <div className="report-print-content">
                <Card className="no-print mb-4">
                    <CardBody>
                        <div className={`grid gap-3 ${showBranchFilter ? 'sm:grid-cols-4' : 'sm:grid-cols-3'}`}>
                            <TableSearchInput
                                inputRef={inputRef}
                                defaultSearch={defaultSearch}
                                onInput={onInput}
                                isSearching={isSearching}
                                placeholder="Search bond #, principal, obligee..."
                                wrapperClassName="relative w-full sm:col-span-3"
                                className="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-sterling-gold focus:ring-sterling-gold"
                            />
                            <select value={status} onChange={handleStatusChange} className="rounded-md border-slate-300 text-sm">
                                <option value="">All statuses</option>
                                {statusOptions.map((o) => (
                                    <option key={o.value} value={o.value}>
                                        {o.label}
                                    </option>
                                ))}
                            </select>
                            <select
                                value={bondTypeId}
                                onChange={handleBondTypeChange}
                                className="rounded-md border-slate-300 text-sm"
                            >
                                <option value="">All types</option>
                                {bondTypeOptions.map((o) => (
                                    <option key={o.value} value={o.value}>
                                        {o.label}
                                    </option>
                                ))}
                            </select>
                            {showBranchFilter && (
                                <BranchFilter
                                    value={branchId}
                                    onChange={handleBranchChange}
                                    branchOptions={branchOptions}
                                    className="rounded-md border-slate-300 text-sm"
                                />
                            )}
                        </div>
                    </CardBody>
                </Card>

                <Card className="dashboard-report-card">
                    <CardBody className="overflow-x-auto p-0">
                        <table className="dashboard-report-table min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-4 py-3 text-left font-medium text-slate-600">Bond #</th>
                                    <th className="px-4 py-3 text-left font-medium text-slate-600">Type</th>
                                    <th className="px-4 py-3 text-left font-medium text-slate-600">Principal</th>
                                    <th className="px-4 py-3 text-left font-medium text-slate-600">Obligee</th>
                                    <th className="px-4 py-3 text-left font-medium text-slate-600">Requester</th>
                                    <th className="px-4 py-3 text-left font-medium text-slate-600">Approver</th>
                                    <th className="print-amount px-4 py-3 text-left font-medium text-slate-600">Amount</th>
                                    <th className="px-4 py-3 text-left font-medium text-slate-600">Status</th>
                                    <th className="print-hide-actions-col px-4 py-3 text-right font-medium text-slate-600">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {bondRequests.data.map((bond) => {
                                    const statusValue = bond.status?.value || bond.status;
                                    const statusOption = statusOptions.find((option) => option.value === statusValue);

                                    return (
                                        <tr key={bond.id} className="hover:bg-slate-50">
                                            <td className="px-4 py-3 font-medium print:font-normal">{bond.bond_number}</td>
                                            <td className="px-4 py-3 capitalize">{bond.bond_type}</td>
                                            <td className="px-4 py-3">
                                                {bond.principal?.company_name ?? bond.principal_name}
                                            </td>
                                            <td className="px-4 py-3">{bond.obligee_name ?? bond.obligee?.company_name}</td>
                                            <td className="px-4 py-3">{bond.creator?.name ?? '—'}</td>
                                            <td className="px-4 py-3">{approverName(bond)}</td>
                                            <td className="print-amount px-4 py-3">{php(bond.amount)}</td>
                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    label={statusOption?.label || bond.status}
                                                    color={statusOption?.color || 'gray'}
                                                />
                                            </td>
                                            <td className="print-hide-actions-col px-4 py-3 text-right">
                                                <div className="flex flex-wrap items-center justify-end gap-3">
                                                    <Link
                                                        href={route('bond-requests.show', bond.id)}
                                                        className="text-sterling-green hover:underline"
                                                    >
                                                        View
                                                    </Link>
                                                    {bond.has_docx && (
                                                        <FileDownloadLink
                                                            href={route('bond-requests.download-docx', bond.id)}
                                                            className="text-slate-500 hover:underline"
                                                        >
                                                            DOCX
                                                        </FileDownloadLink>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </CardBody>
                    <div className="no-print border-t border-slate-100 px-4 py-3">
                        <Pagination links={bondRequests.links} />
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
