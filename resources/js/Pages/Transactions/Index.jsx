import Pagination from '@/Components/UI/Pagination';
import TableSearchInput from '@/Components/UI/TableSearchInput';
import useDebouncedInertiaSearch from '@/hooks/useDebouncedInertiaSearch';
import { visitTable } from '@/lib/visitTable';
import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';

const TABLE_PROPS = ['transactions', 'filters'];

const php = (v) => Number(v).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });

export default function TransactionsIndex({ transactions, isAdmin, filters, userBalance }) {
    const url = route('payments.transactions.index');

    const { inputRef, isSearching, onInput, getValue, defaultSearch } = useDebouncedInertiaSearch({
        initialSearch: filters.search,
        url,
        buildParams: (searchValue) => ({
            search: searchValue.trim() || undefined,
            type: filters.type || undefined,
        }),
        only: TABLE_PROPS,
    });

    const handleTypeFilter = (e) => {
        visitTable(url, {
            search: getValue().trim() || undefined,
            type: e.target.value || undefined,
        }, TABLE_PROPS, { inputRef });
    };

    return (
        <AppLayout title={isAdmin ? 'Transactions' : 'My Transactions'}>
            <Head title="Transactions" />

            {!isAdmin && (
                <div className="mb-6 rounded-xl border-2 border-sterling-gold/40 bg-sterling-gold-50 px-6 py-4">
                    <p className="text-sm font-medium text-sterling-green">Current Balance</p>
                    <p className="mt-1 text-3xl font-bold text-sterling-green-darker">{php(userBalance)}</p>
                </div>
            )}

            <div className="mb-4 flex flex-wrap gap-3">
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
            </div>

            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50">
                        <tr>
                            {[isAdmin && 'User', 'Transaction #', 'Type', 'Amount', 'Balance After', 'Description', 'Date'].filter(Boolean).map((h) => (
                                <th key={h} className="px-4 py-3 text-left text-xs font-medium text-slate-500">{h}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {transactions.data.map((t) => (
                            <tr key={t.id} className="hover:bg-slate-50">
                                {isAdmin && <td className="px-4 py-3 font-medium text-slate-900">{t.user?.name}</td>}
                                <td className="px-4 py-3 font-mono text-xs font-medium text-sterling-green">{t.transaction_number}</td>
                                <td className="px-4 py-3">
                                    <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                        t.type === 'credit' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                    }`}>
                                        {t.type === 'credit' ? '↑ Credit' : '↓ Debit'}
                                    </span>
                                </td>
                                <td className={`px-4 py-3 font-semibold ${t.type === 'credit' ? 'text-emerald-700' : 'text-red-600'}`}>
                                    {t.type === 'credit' ? '+' : '-'}{php(t.amount)}
                                </td>
                                <td className="px-4 py-3 text-slate-600">{php(t.balance_after)}</td>
                                <td className="px-4 py-3 text-slate-600">{t.description}</td>
                                <td className="px-4 py-3 text-slate-500">{new Date(t.created_at).toLocaleDateString('en-PH')}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {transactions.data.length === 0 && (
                    <p className="px-6 py-8 text-center text-sm text-slate-500">No transactions found.</p>
                )}
            </div>

            <Pagination links={transactions.links} meta={transactions.meta} />
        </AppLayout>
    );
}
