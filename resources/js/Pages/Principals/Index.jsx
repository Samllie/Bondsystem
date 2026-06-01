import PrimaryButton from '@/Components/PrimaryButton';
import Card, { CardBody } from '@/Components/UI/Card';
import ConfirmModal from '@/Components/UI/ConfirmModal';
import Pagination from '@/Components/UI/Pagination';
import TableSearchInput from '@/Components/UI/TableSearchInput';
import useDebouncedInertiaSearch from '@/hooks/useDebouncedInertiaSearch';
import { usePermission } from '@/hooks/usePermission';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const TABLE_PROPS = ['principals', 'filters'];

export default function Index({ principals, filters }) {
    const { can } = usePermission();
    const [deleteId, setDeleteId] = useState(null);
    const url = route('principals.index');

    const { inputRef, isSearching, onInput, getValue, defaultSearch } = useDebouncedInertiaSearch({
        initialSearch: filters.search,
        url,
        buildParams: (searchValue) => ({
            search: searchValue.trim() || undefined,
        }),
        only: TABLE_PROPS,
    });

    return (
        <AppLayout
            title="Principals"
            actions={
                can('principals.create') && (
                    <Link href={route('principals.create')}>
                        <PrimaryButton>Add Principal</PrimaryButton>
                    </Link>
                )
            }
        >
            <Head title="Principals" />
            <Card className="mb-4">
                <CardBody>
                    <TableSearchInput
                        inputRef={inputRef}
                        defaultSearch={defaultSearch}
                        onInput={onInput}
                        isSearching={isSearching}
                        placeholder="Search principals..."
                        wrapperClassName="relative w-full"
                        className="w-full rounded-md border-slate-300 text-sm"
                    />
                </CardBody>
            </Card>
            <Card>
                <CardBody className="overflow-x-auto p-0">
                    <table className="min-w-full text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left">Company</th>
                                <th className="px-4 py-3 text-left">Contact</th>
                                <th className="px-4 py-3 text-left">Email</th>
                                <th className="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {principals.data.map((p) => (
                                <tr key={p.id}>
                                    <td className="px-4 py-3 font-medium">{p.company_name}</td>
                                    <td className="px-4 py-3">{p.contact_person}</td>
                                    <td className="px-4 py-3">{p.email}</td>
                                    <td className="space-x-2 px-4 py-3 text-right">
                                        <Link href={route('principals.show', p.id)} className="text-sterling-green">
                                            View
                                        </Link>
                                        {can('principals.update') && (
                                            <Link href={route('principals.edit', p.id)} className="text-slate-600">
                                                Edit
                                            </Link>
                                        )}
                                        {can('principals.delete') && (
                                            <button type="button" onClick={() => setDeleteId(p.id)} className="text-red-600">
                                                Delete
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </CardBody>
                <div className="px-4 py-3">
                    <Pagination links={principals.links} />
                </div>
            </Card>
            <ConfirmModal
                show={!!deleteId}
                onClose={() => setDeleteId(null)}
                onConfirm={() => router.delete(route('principals.destroy', deleteId))}
                title="Delete Principal"
                message="Remove this principal record?"
                confirmLabel="Delete"
                danger
            />
        </AppLayout>
    );
}
