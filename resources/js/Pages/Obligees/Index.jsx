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

const TABLE_PROPS = ['obligees', 'filters'];

export default function Index({ obligees, filters }) {
    const { can } = usePermission();
    const [deleteId, setDeleteId] = useState(null);
    const url = route('obligees.index');

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
            title="Obligees"
            actions={
                can('obligees.create') && (
                    <Link href={route('obligees.create')}><PrimaryButton>Add Obligee</PrimaryButton></Link>
                )
            }
        >
            <Head title="Obligees" />
            <Card className="mb-4">
                <CardBody>
                    <TableSearchInput
                        inputRef={inputRef}
                        defaultSearch={defaultSearch}
                        onInput={onInput}
                        isSearching={isSearching}
                        placeholder="Search obligees..."
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
                            {obligees.data.map((o) => (
                                <tr key={o.id}>
                                    <td className="px-4 py-3 font-medium">{o.company_name}</td>
                                    <td className="px-4 py-3">{o.contact_person}</td>
                                    <td className="px-4 py-3">{o.email}</td>
                                    <td className="px-4 py-3 text-right space-x-2">
                                        <Link href={route('obligees.show', o.id)} className="text-sterling-green">View</Link>
                                        {can('obligees.update') && <Link href={route('obligees.edit', o.id)} className="text-slate-600">Edit</Link>}
                                        {can('obligees.delete') && <button type="button" onClick={() => setDeleteId(o.id)} className="text-red-600">Delete</button>}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </CardBody>
                <div className="px-4 py-3"><Pagination links={obligees.links} /></div>
            </Card>
            <ConfirmModal show={!!deleteId} onClose={() => setDeleteId(null)} onConfirm={() => router.delete(route('obligees.destroy', deleteId))} title="Delete Obligee" message="Remove this obligee record?" confirmLabel="Delete" danger />
        </AppLayout>
    );
}
