import Pagination from '@/Components/UI/Pagination';
import TableSearchInput from '@/Components/UI/TableSearchInput';
import useDebouncedInertiaSearch from '@/hooks/useDebouncedInertiaSearch';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

const TABLE_PROPS = ['records', 'filters'];

export default function SignatoriesIndex({ records, filters, canManage }) {
    const [deleteId, setDeleteId] = useState(null);
    const { delete: destroy, processing } = useForm();
    const url = route('maintenance.signatories.index');

    const { inputRef, isSearching, onInput, getValue, defaultSearch } = useDebouncedInertiaSearch({
        initialSearch: filters.search,
        url,
        buildParams: (searchValue) => ({
            search: searchValue.trim() || undefined,
        }),
        only: TABLE_PROPS,
    });

    const confirmDelete = (id) => setDeleteId(id);
    const doDelete = () => {
        destroy(route('maintenance.signatories.destroy', deleteId), { onSuccess: () => setDeleteId(null) });
    };

    return (
        <AppLayout
            title="Signatories"
            actions={
                canManage && (
                    <Link
                        href={route('maintenance.signatories.create')}
                        className="inline-flex items-center gap-1.5 rounded-lg bg-sterling-gold px-4 py-2 text-sm font-semibold text-sterling-green-darker hover:bg-sterling-gold-light"
                    >
                        + New Signatory
                    </Link>
                )
            }
        >
            <Head title="Signatories" />

            <TableSearchInput
                inputRef={inputRef}
                defaultSearch={defaultSearch}
                onInput={onInput}
                isSearching={isSearching}
                placeholder="Search signatories…"
                wrapperClassName="relative mb-4 w-full max-w-md"
                className="rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sterling-gold focus:outline-none"
            />

            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50">
                        <tr>
                            <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Name</th>
                            <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Position</th>
                            <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">TIN</th>
                            <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Signature</th>
                            {canManage && <th className="px-4 py-3"></th>}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {records.data.map((signatory) => (
                            <tr key={signatory.id} className="hover:bg-slate-50">
                                <td className="px-4 py-3 font-medium text-slate-900">{signatory.name}</td>
                                <td className="px-4 py-3 text-slate-600">{signatory.position}</td>
                                <td className="px-4 py-3 font-mono text-xs text-slate-600">{signatory.tin}</td>
                                <td className="px-4 py-3">
                                    {signatory.signature_url ? (
                                        <img
                                            src={signatory.signature_url}
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
                                                href={route('maintenance.signatories.edit', signatory.id)}
                                                className="text-xs text-sterling-green hover:underline"
                                            >
                                                Edit
                                            </Link>
                                            <button
                                                type="button"
                                                onClick={() => confirmDelete(signatory.id)}
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
                    <p className="px-6 py-8 text-center text-sm text-slate-500">No signatories found.</p>
                )}
            </div>

            <Pagination links={records.links} meta={records.meta} />

            {deleteId && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl">
                        <h3 className="text-lg font-semibold text-slate-900">Delete Signatory?</h3>
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
