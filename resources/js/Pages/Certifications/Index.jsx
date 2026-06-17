import BranchFilter from '@/Components/Report/BranchFilter';
import CertificateScanModal from '@/Components/Certifications/CertificateScanModal';
import PrintReportButton from '@/Components/Report/PrintReportButton';
import ReportPrintHeader from '@/Components/Report/ReportPrintHeader';
import Pagination from '@/Components/UI/Pagination';
import TableSearchInput from '@/Components/UI/TableSearchInput';
import useDebouncedInertiaSearch from '@/hooks/useDebouncedInertiaSearch';
import {
    getCameraErrorMessage,
    isCameraScanSupported,
    requestCameraStream,
} from '@/lib/certificateScan';
import { visitTable } from '@/lib/visitTable';
import { certificationFilterSummary } from '@/lib/reportPrint';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

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
    pageTitle = 'Confirmations',
    scopeMessage,
    readOnly = false,
}) {
    const [scanOpen, setScanOpen] = useState(false);
    const [scanStream, setScanStream] = useState(null);
    const [scanError, setScanError] = useState('');
    const url = listUrl ?? (context === 'maintenance'
        ? route('maintenance.certifications.index')
        : route('certifications.index'));
    const filterSummary = certificationFilterSummary(filters, branchOptions);
    const reportTitle = context === 'maintenance' ? 'Confirmations Report' : 'Confirmations Report';

    const defaultScopeMessage = canViewAllBranches
        ? showBranchFilter
            ? 'Showing generated confirmations across all branches. Use the branch filter to narrow results.'
            : 'Showing generated confirmations across all branches.'
        : `Showing generated confirmations for your branch${branchName ? ` (${branchName})` : ''}.`;

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

    const handleOpenScan = async () => {
        setScanError('');
        setScanOpen(true);

        if (! isCameraScanSupported()) {
            return;
        }

        try {
            const stream = await requestCameraStream();
            setScanStream(stream);
        } catch (error) {
            setScanStream(null);
            setScanError(getCameraErrorMessage(error));
        }
    };

    const handleCloseScan = () => {
        scanStream?.getTracks().forEach((track) => track.stop());
        setScanStream(null);
        setScanError('');
        setScanOpen(false);
    };

    const handleScanSuccess = (searchValue) => {
        if (inputRef.current) {
            inputRef.current.value = searchValue;
        }

        visitTable(url, {
            search: searchValue,
            branch_id: filters.branch_id || undefined,
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
                            placeholder="Search bond #, CAR #, confirmation #, obligee, principal..."
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
                        <button
                            type="button"
                            onClick={handleOpenScan}
                            className="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                        >
                            Scan QR
                        </button>
                        {scanError && (
                            <p className="w-full text-sm text-red-600">{scanError}</p>
                        )}
                    </div>
                </div>

                <CertificateScanModal
                    show={scanOpen}
                    onClose={handleCloseScan}
                    onScanSuccess={handleScanSuccess}
                    initialStream={scanStream}
                />

                <div className="dashboard-report-card overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="dashboard-report-table min-w-full text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Bond / CAR #</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Confirmation #</th>
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
                                    <td colSpan={10} className="px-4 py-10 text-center text-slate-500">
                                        No confirmations found.
                                    </td>
                                </tr>
                            )}
                            {certificates.data.map((cert) => (
                                <tr
                                    key={cert.id}
                                    className={readOnly ? 'hover:bg-slate-50' : 'cursor-pointer hover:bg-slate-50'}
                                    onClick={readOnly ? undefined : () => router.visit(route('bond-requests.show', cert.id))}
                                >
                                    <td className="px-4 py-3 font-medium text-slate-900">
                                        {readOnly ? (
                                            cert.bond_label || cert.bond_number
                                        ) : (
                                            <Link
                                                href={route('bond-requests.show', cert.id)}
                                                className="text-sterling-green hover:underline"
                                                onClick={(event) => event.stopPropagation()}
                                            >
                                                {cert.bond_label || cert.bond_number}
                                            </Link>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-slate-700">
                                        {cert.confirmation_number || '—'}
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
                                            {!readOnly && (
                                                <Link
                                                    href={route('bond-requests.show', cert.id)}
                                                    className="text-sterling-green hover:underline"
                                                >
                                                    Review
                                                </Link>
                                            )}
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
