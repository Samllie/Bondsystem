import PrimaryButton from '@/Components/PrimaryButton';
import Card, { CardBody } from '@/Components/UI/Card';
import Pagination from '@/Components/UI/Pagination';
import TableSearchInput from '@/Components/UI/TableSearchInput';
import useDebouncedInertiaSearch from '@/hooks/useDebouncedInertiaSearch';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';

const TABLE_PROPS = ['users', 'filters'];

export default function Index({ users, filters, canManage }) {
    const url = route('users.index');

    const { inputRef, isSearching, onInput, defaultSearch } = useDebouncedInertiaSearch({
        initialSearch: filters.search,
        url,
        buildParams: (searchValue) => ({
            search: searchValue.trim() || undefined,
        }),
        only: TABLE_PROPS,
    });

    return (
        <AppLayout
            title="Users"
            actions={
                canManage && (
                    <Link href={route('users.create')}>
                        <PrimaryButton>Add User</PrimaryButton>
                    </Link>
                )
            }
        >
            <Head title="Users" />

            <Card className="mb-4">
                <CardBody>
                    <TableSearchInput
                        inputRef={inputRef}
                        defaultSearch={defaultSearch}
                        onInput={onInput}
                        isSearching={isSearching}
                        placeholder="Search users…"
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
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Name</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Email</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Account Level</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Branch</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Branch City</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {users.data.map((user) => (
                                <tr key={user.id} className="hover:bg-slate-50">
                                    <td className="px-4 py-3 font-medium text-slate-900">{user.name}</td>
                                    <td className="px-4 py-3 text-slate-600">{user.email}</td>
                                    <td className="px-4 py-3 text-slate-600">{user.role?.name ?? '—'}</td>
                                    <td className="px-4 py-3 text-slate-600">{user.branch?.name ?? '—'}</td>
                                    <td className="px-4 py-3 text-slate-600">{user.branch_city ?? '—'}</td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                user.is_active
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-slate-100 text-slate-600'
                                            }`}
                                        >
                                            {user.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {users.data.length === 0 && (
                        <p className="px-6 py-8 text-center text-sm text-slate-500">No users found.</p>
                    )}
                </CardBody>
            </Card>

            <Pagination links={users.links} meta={users.meta} />
        </AppLayout>
    );
}
