import BranchFilter from '@/Components/Report/BranchFilter';
import PrintReportButton from '@/Components/Report/PrintReportButton';
import ReportPrintHeader from '@/Components/Report/ReportPrintHeader';
import Pagination from '@/Components/UI/Pagination';
import TableSearchInput from '@/Components/UI/TableSearchInput';
import useDebouncedInertiaSearch from '@/hooks/useDebouncedInertiaSearch';
import { visitTable } from '@/lib/visitTable';
import { transactionFilterSummary } from '@/lib/reportPrint';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Modal from '@/Components/Modal';
import { TextAreaField } from '@/Components/UI/FormField';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';

const TABLE_PROPS = ['transactions', 'filters'];
const php = (v) => Number(v).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });

export default function TransactionsIndex({
    transactions,
    isAdmin,
    filters,
    userBalance,
    branchName,
    branchOptions,
    showBranchFilter,
    canReturnFund,
}) {
    const [returnFundOpen, setReturnFundOpen] = useState(false);
    const [selectedTransaction, setSelectedTransaction] = useState(null);
    
    const returnFundForm = useForm({
        remarks: '',
    });

    const submitReturnFund = (e) => {
        e.preventDefault();
        if (!selectedTransaction?.bondRequest?.id) return;

        returnFundForm.post(route('bond-requests.return-fund', selectedTransaction.bondRequest.id), {
            onSuccess: () => {
                setReturnFundOpen(false);
                returnFundForm.reset();
                setSelectedTransaction(null);
            },
        });
    };

    const isNotaryFeeDebit = (transaction) => {
        return (
            transaction.type === 'debit' &&
            transaction.description?.includes('Notary fee') &&
            transaction.bondRequest
        );
    };

    const openReturnFundModal = (transaction) => {
        setSelectedTransaction(transaction);
        setReturnFundOpen(true);
    };
    const url = route('payments.transactions.index');
    const filterSummary = transactionFilterSummary(filters, branchOptions);
    const pageTitle = isAdmin ? 'Transactions' : 'My Transactions';

    const { inputRef, isSearching, onInput, getValue, defaultSearch } = useDebouncedInertiaSearch({
        initialSearch: filters.search,
        url,
        buildParams: (searchValue) => ({
            search: searchValue.trim() || undefined,
            type: filters.type || undefined,
            branch_id: filters.branch_id || undefined,
        }),
        only: TABLE_PROPS,
    });

    const handleTypeFilter = (e) => {
        visitTable(url, {
            search: getValue().trim() || undefined,
            type: e.target.value || undefined,
            branch_id: filters.branch_id || undefined,
        }, TABLE_PROPS, { inputRef });
    };

    const handleBranchFilter = (e) => {
        visitTable(url, {
            search: getValue().trim() || undefined,
            type: filters.type || undefined,
            branch_id: e.target.value || undefined,
        }, TABLE_PROPS, { inputRef });
    };

    return (
        <AppLayout
            title={pageTitle}
            actions={<PrintReportButton />}
        >
            <Head title="Transactions" />

            <ReportPrintHeader title={`${pageTitle} Report`} filterSummary={filterSummary} />

            <div className="report-print-content">
                {!isAdmin && (
                    <div className="no-print mb-6 rounded-xl border-2 border-sterling-gold/40 bg-sterling-gold-50 px-6 py-4">
                        <p className="text-sm font-medium text-sterling-green">
                            {branchName ? `${branchName} Fund` : 'Branch Fund'}
                        </p>
                        <p className="mt-1 text-3xl font-bold text-sterling-green-darker">{php(userBalance)}</p>
                    </div>
                )}

                <div className="no-print mb-4 flex flex-wrap gap-3">
                    <TableSearchInput
                        inputRef={inputRef}
                        defaultSearch={defaultSearch}
                        onInput={onInput}
                        isSearching={isSearching}
                        placeholder="Search by transaction number…"
                        className="rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sterling-gold focus:outline-none"
                    />
                    <select
                        value={filters.type ?? ''}
                        onChange={handleTypeFilter}
                        className="rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sterling-gold focus:outline-none"
                    >
                        <option value="">All Types</option>
                        <option value="credit">Credit</option>
                        <option value="debit">Debit</option>
                    </select>
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
                                {[
                                    isAdmin && 'User',
                                    'Transaction #',
                                    'Type',
                                    'Amount',
                                    'Balance After',
                                    'Description',
                                    'Date',
                                    canReturnFund && 'Actions',
                                ]
                                    .filter(Boolean)
                                    .map((heading) => (
                                        <th
                                            key={heading}
                                            className={`px-4 py-3 text-left text-xs font-medium text-slate-500 ${
                                                heading === 'Amount' || heading === 'Balance After' ? 'print-amount' : ''
                                            }`}
                                        >
                                            {heading}
                                        </th>
                                    ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {transactions.data.map((t) => (
                                <tr key={t.id} className="hover:bg-slate-50">
                                    {isAdmin && <td className="px-4 py-3 font-medium text-slate-900">{t.user?.name}</td>}
                                    <td className="px-4 py-3 font-mono text-xs font-medium text-sterling-green print:font-sans print:text-sm print:text-slate-900">
                                        {t.transaction_number}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={`print-type inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                t.type === 'credit' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                            }`}
                                        >
                                            {t.type === 'credit' ? '↑ Credit' : '↓ Debit'}
                                        </span>
                                    </td>
                                    <td
                                        className={`print-amount px-4 py-3 font-semibold ${
                                            t.type === 'credit' ? 'text-emerald-700' : 'text-red-600'
                                        } print:text-slate-900`}
                                    >
                                        {t.type === 'credit' ? '+' : '-'}
                                        {php(t.amount)}
                                    </td>
                                    <td className="print-amount px-4 py-3 text-slate-600">{php(t.balance_after)}</td>
                                    <td className="px-4 py-3 text-slate-600">{t.description}</td>
                                    <td className="px-4 py-3 text-slate-500">
                                        {new Date(t.created_at).toLocaleDateString('en-PH')}
                                    </td>
                                    {canReturnFund && isNotaryFeeDebit(t) && (
                                        <td className="no-print px-4 py-3 text-sm">
                                            <button
                                                type="button"
                                                onClick={() => openReturnFundModal(t)}
                                                className="text-sterling-green hover:text-sterling-green-darker font-medium"
                                            >
                                                Return Fund
                                            </button>
                                        </td>
                                    )}
                                    {canReturnFund && !isNotaryFeeDebit(t) && (
                                        <td className="no-print px-4 py-3" />
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {transactions.data.length === 0 && (
                        <p className="px-6 py-8 text-center text-sm text-slate-500">No transactions found.</p>
                    )}
                </div>

                <div className="no-print">
                    <Pagination links={transactions.links} meta={transactions.meta} />
                </div>
            </div>

            <Modal show={returnFundOpen} onClose={() => setReturnFundOpen(false)} maxWidth="md">
                <form onSubmit={submitReturnFund} className="p-6">
                    <h3 className="text-lg font-semibold text-sterling-green">Return Fund</h3>
                    <p className="mt-2 text-sm text-slate-600">
                        This will return the full notary fee to the requester&apos;s branch and mark the request as returned.
                    </p>
                    {selectedTransaction && (
                        <p className="mt-2 text-sm text-slate-600">
                            <strong>Bond Request:</strong> {selectedTransaction.bondRequest?.bond_number}
                        </p>
                    )}
                    <div className="mt-4 space-y-2">
                        <TextAreaField
                            label="Remarks / Feedback"
                            value={returnFundForm.data.remarks}
                            onChange={(e) => returnFundForm.setData('remarks', e.target.value)}
                            rows={4}
                            className="min-h-[120px] resize-y"
                            error={returnFundForm.errors.remarks}
                            placeholder="Optional reason or notes for the fund return"
                        />
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton
                            type="button"
                            onClick={() => {
                                setReturnFundOpen(false);
                                returnFundForm.reset();
                                setSelectedTransaction(null);
                            }}
                        >
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton type="submit" disabled={returnFundForm.processing}>
                            {returnFundForm.processing ? 'Processing…' : 'Return Fund'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </AppLayout>
    );
}
