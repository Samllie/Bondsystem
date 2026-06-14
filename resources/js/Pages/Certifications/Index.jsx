import BranchFilter from '@/Components/Report/BranchFilter';
import PrintReportButton from '@/Components/Report/PrintReportButton';
import ReportPrintHeader from '@/Components/Report/ReportPrintHeader';
import Pagination from '@/Components/UI/Pagination';
import TableSearchInput from '@/Components/UI/TableSearchInput';
import useDebouncedInertiaSearch from '@/hooks/useDebouncedInertiaSearch';
import { visitTable } from '@/lib/visitTable';
import { certificationFilterSummary } from '@/lib/reportPrint';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';

const TABLE_PROPS = ['certificates', 'filters'];

export default function Index({
    certificates,
    filters,
    canViewAllBranches,
    branchName,
    branchOptions,
    showBranchFilter,
    generatedAt,
    context = 'user',
    listUrl,
    pageTitle = 'Certifications',
    scopeMessage,
}) {
    const url = listUrl ?? (context === 'maintenance'
        ? route('maintenance.certifications.index')
        : route('certifications.index'));
    const filterSummary = certificationFilterSummary(filters, branchOptions);
    const reportTitle = context === 'maintenance' ? 'Certification Report' : 'Certifications Report';

    const defaultScopeMessage = canViewAllBranches
        ? showBranchFilter
            ? 'Showing generated certificates across all branches. Use the branch filter to narrow results.'
            : 'Showing generated certificates across all branches.'
        : `Showing generated certificates for your branch${branchName ? ` (${branchName})` : ''}.`;

    const { inputRef, isSearching, onInput, getValue, defaultSearch } = useDebouncedInertiaSearch({
        initialSearch: filters.search,
        url,
        buildParams: (searchValue) => ({
            search: searchValue.trim() || undefined,
            branch_id: filters.branch_id || undefined,
        }),
        only: TABLE_PROPS,
    });

    const handleBranchFilter = (e) => {
        visitTable(url, {
            search: getValue().trim() || undefined,
            branch_id: e.target.value || undefined,
        }, TABLE_PROPS, { inputRef });
    };

    return (
        <AppLayout title={pageTitle} actions={<PrintReportButton />}>
            <Head title={pageTitle} />

            <ReportPrintHeader
                title={reportTitle}
                filterSummary={filterSummary}
                generatedAt={generatedAt}
            />

            <div className="report-print-content">
                <div className="no-print mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p className="mb-3 text-sm text-slate-600">{scopeMessage ?? defaultScopeMessage}</p>
                    <div className="flex flex-wrap gap-3">
                        <TableSearchInput
                            inputRef={inputRef}
                            defaultSearch={defaultSearch}
                            onInput={onInput}
                            isSearching={isSearching}
                            placeholder="Search bond #, CAR #, obligee, principal..."
                            wrapperClassName="relative flex-1 min-w-[200px]"
                            className="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-sterling-gold focus:ring-sterling-gold"
                        />
                        {showBranchFilter && (
                            <BranchFilter
                                value={filters.branch_id ?? ''}
                                onChange={handleBranchFilter}
                                branchOptions={branchOptions}
                                className="rounded-md border-slate-300 text-sm shadow-sm focus:border-sterling-gold focus:ring-sterling-gold"
                            />
                        )}
                    </div>
                </div>

                <div className="dashboard-report-card overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="dashboard-report-table min-w-full text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Bond / CAR #</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Type</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Obligee</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Principal</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Branch</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Requester</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Approver</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Date</th>
                                <th className="print-hide-actions-col px-4 py-3 text-right text-xs font-medium text-slate-500">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {certificates.data.length === 0 && (
                                <tr>
                                    <td colSpan={9} className="px-4 py-10 text-center text-slate-500">
                                        No certificates found.
                                    </td>
                                </tr>
                            )}
                            {certificates.data.map((cert) => (
                                <tr
                                    key={cert.id}
                                    className="cursor-pointer hover:bg-slate-50"
                                    onClick={() => router.visit(route('bond-requests.show', cert.id))}
                                >
                                    <td className="px-4 py-3 font-medium text-slate-900">
                                        <Link
                                            href={route('bond-requests.show', cert.id)}
                                            className="text-sterling-green hover:underline"
                                            onClick={(event) => event.stopPropagation()}
                                        >
                                            {cert.bond_label || cert.bond_number}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3 text-slate-700">{cert.certificate_type_label}</td>
                                    <td className="px-4 py-3 text-slate-700">{cert.obligee_name || '—'}</td>
                                    <td className="px-4 py-3 text-slate-700">{cert.principal_name || '—'}</td>
                                    <td className="px-4 py-3 text-slate-700">{cert.branch_name}</td>
                                    <td className="px-4 py-3 text-slate-700">{cert.requester_name || '—'}</td>
                                    <td className="px-4 py-3 text-slate-700">{cert.approver_name || '—'}</td>
                                    <td className="px-4 py-3 text-slate-500">{cert.request_date || '—'}</td>
                                    <td className="print-hide-actions-col px-4 py-3 text-right">
                                        <div className="flex justify-end gap-3" onClick={(event) => event.stopPropagation()}>
                                            <Link
                                                href={route('bond-requests.show', cert.id)}
                                                className="text-sterling-green hover:underline"
                                            >
                                                Review
                                            </Link>
                                            <a
                                                href={route('bond-requests.view-certificate', cert.id)}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="text-sterling-green hover:underline"
                                            >
                                                View
                                            </a>
                                            <a
                                                href={route('bond-requests.download-certificate', cert.id)}
                                                className="text-sterling-green hover:underline"
                                            >
                                                Download
                                            </a>
                                            {cert.has_docx && (
                                                <a
                                                    href={route('bond-requests.download-docx', cert.id)}
                                                    className="text-slate-500 hover:underline"
                                                >
                                                    DOCX
                                                </a>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="no-print border-t border-slate-100 px-4 py-3">
                    <Pagination links={certificates.links} meta={certificates.meta} />
                </div>
            </div>
        </AppLayout>
    );
}
