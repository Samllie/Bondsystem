import Pagination from '@/Components/UI/Pagination';
import TableSearchInput from '@/Components/UI/TableSearchInput';
import useDebouncedInertiaSearch from '@/hooks/useDebouncedInertiaSearch';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

const TABLE_PROPS = ['records', 'filters'];

export default function NotariesIndex({ records, filters, canManage }) {
    const [deleteId, setDeleteId] = useState(null);
    const { delete: destroy, processing } = useForm();
    const url = route('maintenance.notaries.index');

    const { inputRef, isSearching, onInput, defaultSearch } = useDebouncedInertiaSearch({
        initialSearch: filters.search,
        url,
        buildParams: (searchValue) => ({
            search: searchValue.trim() || undefined,
        }),
        only: TABLE_PROPS,
    });

    const confirmDelete = (id) => setDeleteId(id);
    const doDelete = () => {
        destroy(route('maintenance.notaries.destroy', deleteId), { onSuccess: () => setDeleteId(null) });
    };

    return (
        <AppLayout
            title="Notaries"
            actions={
                canManage && (
                    <Link
                        href={route('maintenance.notaries.create')}
                        className="inline-flex items-center gap-1.5 rounded-lg bg-sterling-gold px-4 py-2 text-sm font-semibold text-sterling-green-darker hover:bg-sterling-gold-light"
                    >
                        + New Notary
                    </Link>
                )
            }
        >
            <Head title="Notaries" />

            <TableSearchInput
                inputRef={inputRef}
                defaultSearch={defaultSearch}
                onInput={onInput}
                isSearching={isSearching}
                placeholder="Search notaries…"
                wrapperClassName="relative mb-4 w-full max-w-md"
                className="rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sterling-gold focus:outline-none"
            />

            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50">
                        <tr>
                            <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Name</th>
                            <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Commission #</th>
                            <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">TIN</th>
                            <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Seal</th>
                            {canManage && <th className="px-4 py-3"></th>}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {records.data.map((notary) => (
                            <tr key={notary.id} className="hover:bg-slate-50">
                                <td className="px-4 py-3 font-medium text-slate-900">{notary.name}</td>
                                <td className="px-4 py-3 text-slate-600">{notary.commission_number}</td>
                                <td className="px-4 py-3 font-mono text-xs text-slate-600">{notary.tin}</td>
                                <td className="px-4 py-3">
                                    {notary.signature_url ? (
                                        <img
                                            src={notary.signature_url}
                                            alt=""
                                            className="h-10 max-w-[120px] object-contain"
                                        />
                                    ) : (
                                        <span className="text-slate-400">—</span>
                                    )}
                                </td>
                                {canManage && (
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-3">
                                            <Link
                                                href={route('maintenance.notaries.edit', notary.id)}
                                                className="text-xs text-sterling-green hover:underline"
                                            >
                                                Edit
                                            </Link>
                                            <button
                                                type="button"
                                                onClick={() => confirmDelete(notary.id)}
                                                className="text-xs text-red-500 hover:underline"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
                {records.data.length === 0 && (
                    <p className="px-6 py-8 text-center text-sm text-slate-500">No notaries found.</p>
                )}
            </div>

            <Pagination links={records.links} meta={records.meta} />

            {deleteId && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl">
                        <h3 className="text-lg font-semibold text-slate-900">Delete Notary?</h3>
                        <p className="mt-1 text-sm text-slate-500">This action cannot be undone.</p>
                        <div className="mt-5 flex justify-end gap-3">
                            <button
                                type="button"
                                onClick={() => setDeleteId(null)}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={doDelete}
                                disabled={processing}
                                className="rounded-lg bg-red-600 px-5 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
