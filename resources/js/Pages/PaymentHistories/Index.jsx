import BranchFilter from '@/Components/Report/BranchFilter';
import PrintReportButton from '@/Components/Report/PrintReportButton';
import ReportPrintHeader from '@/Components/Report/ReportPrintHeader';
import Pagination from '@/Components/UI/Pagination';
import TableSearchInput from '@/Components/UI/TableSearchInput';
import useDebouncedInertiaSearch from '@/hooks/useDebouncedInertiaSearch';
import { paymentHistoryFilterSummary } from '@/lib/reportPrint';
import { visitTable } from '@/lib/visitTable';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';

const TABLE_PROPS = ['payments', 'filters'];
const php = (v) => Number(v).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });

export default function PaymentHistoriesIndex({ payments, isAdmin, filters, branchOptions, showBranchFilter }) {
    const url = route('payments.histories.index');
    const filterSummary = paymentHistoryFilterSummary(filters, branchOptions);
    const pageTitle = isAdmin ? 'Payment Histories' : 'My Payment Histories';

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
        <AppLayout
            title={pageTitle}
            actions={<PrintReportButton />}
        >
            <Head title="Payment Histories" />

            <ReportPrintHeader title={`${pageTitle} Report`} filterSummary={filterSummary} />

            <div className="report-print-content">
                <p className="no-print mb-4 text-sm text-slate-600">Payments made for bond and document requests.</p>

                <div className="no-print mb-4 flex flex-wrap gap-3">
                    <TableSearchInput
                        inputRef={inputRef}
                        defaultSearch={defaultSearch}
                        onInput={onInput}
                        isSearching={isSearching}
                        placeholder="Search by payment number or bond number…"
                        className="rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sterling-gold focus:outline-none"
                    />
                    {showBranchFilter && (
                        <BranchFilter
                            value={filters.branch_id ?? ''}
                            onChange={handleBranchFilter}
                            branchOptions={branchOptions}
                            className="rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sterling-gold focus:outline-none"
                        />
                    )}
                </div>

                <div className="dashboard-report-card overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="dashboard-report-table min-w-full text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                {[isAdmin && 'User', 'Payment #', 'Bond #', 'Principal', 'Amount', 'Paid At', 'Actions']
                                    .filter(Boolean)
                                    .map((heading) => (
                                        <th
                                            key={heading}
                                            className={`px-4 py-3 text-left text-xs font-medium text-slate-500 ${
                                                heading === 'Amount' ? 'print-amount' : ''
                                            } ${heading === 'Actions' ? 'print-hide-actions-col' : ''}`}
                                        >
                                            {heading}
                                        </th>
                                    ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {payments.data.map((payment) => (
                                <tr key={payment.id} className="hover:bg-slate-50">
                                    {isAdmin && <td className="px-4 py-3 font-medium text-slate-900">{payment.user?.name}</td>}
                                    <td className="px-4 py-3 font-mono text-xs font-semibold text-sterling-green print:font-sans print:text-sm print:text-slate-900">
                                        {payment.payment_number}
                                    </td>
                                    <td className="px-4 py-3 font-medium text-slate-900">{payment.bond_request?.bond_number}</td>
                                    <td className="px-4 py-3 text-slate-600">
                                        {payment.bond_request?.principal?.company_name}
                                    </td>
                                    <td className="print-amount px-4 py-3 font-semibold text-emerald-700 print:text-slate-900">
                                        {php(payment.amount)}
                                    </td>
                                    <td className="px-4 py-3 text-slate-500">
                                        {new Date(payment.paid_at).toLocaleDateString('en-PH')}
                                    </td>
                                    <td className="print-hide-actions-col px-4 py-3">
                                        {payment.bond_request && (
                                            <Link
                                                href={route('bond-requests.show', payment.bond_request.id)}
                                                className="text-sterling-green hover:underline"
                                            >
                                                View Request
                                            </Link>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {payments.data.length === 0 && (
                        <p className="px-6 py-8 text-center text-sm text-slate-500">No payment histories found.</p>
                    )}
                </div>

                <div className="no-print">
                    <Pagination links={payments.links} meta={payments.meta} />
                </div>
            </div>
        </AppLayout>
    );
}
