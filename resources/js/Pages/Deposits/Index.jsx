import BranchFilter from '@/Components/Report/BranchFilter';
import Pagination from '@/Components/UI/Pagination';
import StatusBadge from '@/Components/UI/StatusBadge';
import TableSearchInput from '@/Components/UI/TableSearchInput';
import useDebouncedInertiaSearch from '@/hooks/useDebouncedInertiaSearch';
import { visitTable } from '@/lib/visitTable';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';

const TABLE_PROPS = ['deposits', 'filters'];

const php = (v) => Number(v).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });

const statusColors = { pending: 'amber', approved: 'green', rejected: 'red' };
const statusLabels = { pending: 'Pending', approved: 'Approved', rejected: 'Rejected' };

export default function DepositsIndex({
    deposits,
    isAdmin,
    canSubmit,
    filters,
    statusOptions,
    userBalance,
    branchOptions,
    showBranchFilter,
}) {
    const url = route('payments.deposits.index');

    const { inputRef, isSearching, onInput, getValue, defaultSearch } = useDebouncedInertiaSearch({
        initialSearch: filters.search,
        url,
        buildParams: (searchValue) => ({
            search: searchValue.trim() || undefined,
            status: filters.status || undefined,
            mine: filters.mine || undefined,
            branch_id: filters.branch_id || undefined,
        }),
        only: TABLE_PROPS,
    });

    const applyFilter = (extra) => {
        visitTable(url, {
            search: getValue().trim() || undefined,
            status: extra.status ?? filters.status ?? undefined,
            mine: extra.mine ?? filters.mine ?? undefined,
            branch_id: extra.branch_id ?? filters.branch_id ?? undefined,
        }, TABLE_PROPS, { inputRef });
    };

    const title = isAdmin ? 'Deposit Submissions' : 'My Deposits';

    return (
        <AppLayout
            title={title}
            actions={
                canSubmit && (
                    <Link
                        href={route('payments.deposits.create')}
                        className="inline-flex items-center gap-1.5 rounded-lg bg-sterling-gold px-4 py-2 text-sm font-semibold text-sterling-green-darker hover:bg-sterling-gold-light"
                    >
                        + New Deposit
                    </Link>
                )
            }
        >
            <Head title={title} />

            <div className="mb-6 flex items-center justify-between rounded-xl border-2 border-sterling-gold/40 bg-sterling-gold-50 px-6 py-4">
                <div>
                    <p className="text-sm font-medium text-sterling-green">My Balance</p>
                    <p className="mt-1 text-3xl font-bold text-sterling-green-darker">{php(userBalance)}</p>
                </div>
                {canSubmit && (
                    <Link
                        href={route('payments.deposits.create')}
                        className="rounded-lg bg-sterling-gold px-4 py-2 text-sm font-semibold text-sterling-green-darker hover:bg-sterling-gold-light"
                    >
                        + Deposit Funds
                    </Link>
                )}
            </div>

            {canSubmit && (
                <div className="mb-4 flex gap-2">
                    <button
                        type="button"
                        onClick={() => applyFilter({ mine: undefined })}
                        className={`rounded-lg px-4 py-1.5 text-sm font-medium transition-colors ${
                            isAdmin ? 'bg-sterling-gold text-sterling-green-darker' : 'border border-slate-300 text-slate-600 hover:bg-slate-50'
                        }`}
                    >
                        All Submissions
                    </button>
                    <button
                        type="button"
                        onClick={() => applyFilter({ mine: 1 })}
                        className={`rounded-lg px-4 py-1.5 text-sm font-medium transition-colors ${
                            !isAdmin ? 'bg-sterling-gold text-sterling-green-darker' : 'border border-slate-300 text-slate-600 hover:bg-slate-50'
                        }`}
                    >
                        My Deposits
                    </button>
                </div>
            )}

            <div className="mb-4 flex flex-wrap gap-3">
                <TableSearchInput
                    inputRef={inputRef}
                    defaultSearch={defaultSearch}
                    onInput={onInput}
                    isSearching={isSearching}
                    placeholder="Search by reference #, bank, or requester…"
                    className="rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sterling-gold focus:outline-none"
                />
                <select
                    value={filters.status ?? ''}
                    onChange={(e) => applyFilter({ status: e.target.value || undefined })}
                    className="rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sterling-gold focus:outline-none"
                >
                    <option value="">All Status</option>
                    {statusOptions.map((s) => (
                        <option key={s.value} value={s.value}>{s.label}</option>
                    ))}
                </select>
                {showBranchFilter && (
                    <BranchFilter
                        value={filters.branch_id ?? ''}
                        onChange={(e) => applyFilter({ branch_id: e.target.value || undefined })}
                        branchOptions={branchOptions}
                        className="rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sterling-gold focus:outline-none"
                    />
                )}
            </div>

            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50">
                        <tr>
                            {[isAdmin && 'Requester', 'Bank', 'Amount', 'Reference #', 'Deposit Date', 'Status', ''].filter(Boolean).map((h) => (
                                <th key={h} className="px-4 py-3 text-left text-xs font-medium text-slate-500">{h}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {deposits.data.map((d) => (
                            <tr key={d.id} className="hover:bg-slate-50">
                                {isAdmin && <td className="px-4 py-3 font-medium text-slate-900">{d.user?.name}</td>}
                                <td className="px-4 py-3 text-slate-600">{d.bank_account?.bank_name}</td>
                                <td className="px-4 py-3 font-semibold text-emerald-700">{php(d.amount)}</td>
                                <td className="px-4 py-3 font-mono text-xs text-slate-600">{d.reference_number}</td>
                                <td className="px-4 py-3 text-slate-600">{d.deposit_date}</td>
                                <td className="px-4 py-3">
                                    <StatusBadge label={statusLabels[d.status] ?? d.status} color={statusColors[d.status] ?? 'slate'} />
                                </td>
                                <td className="px-4 py-3">
                                    <Link href={route('payments.deposits.show', d.id)} className="text-sterling-green hover:underline">
                                        {isAdmin && d.status === 'pending' ? 'Review' : 'View'}
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {deposits.data.length === 0 && (
                    <p className="px-6 py-8 text-center text-sm text-slate-500">No deposits found.</p>
                )}
            </div>

            <Pagination links={deposits.links} meta={deposits.meta} />
        </AppLayout>
    );
}
