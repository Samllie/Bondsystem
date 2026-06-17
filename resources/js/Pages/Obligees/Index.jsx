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

const TABLE_PROPS = [
    'obligees',
    'kycObligees',
    'certificateObligeesFromKyc',
    'certificateObligeesTyped',
    'filters',
    'kycView',
    'branchConfirmationsView',
    'branchName',
];

function ObligeeTable({ title, description, columns, rows, emptyMessage, paginationLinks }) {
    return (
        <Card className="mb-6">
            <CardBody className="border-b border-slate-100 px-4 py-4">
                <h3 className="text-base font-semibold text-slate-900">{title}</h3>
                {description && <p className="mt-1 text-sm text-slate-600">{description}</p>}
            </CardBody>
            <CardBody className="overflow-x-auto p-0">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50">
                        <tr>
                            {columns.map((column) => (
                                <th key={column.key} className={`px-4 py-3 text-left ${column.className ?? ''}`}>
                                    {column.label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {rows.length === 0 ? (
                            <tr>
                                <td colSpan={columns.length} className="px-4 py-8 text-center text-slate-500">
                                    {emptyMessage}
                                </td>
                            </tr>
                        ) : (
                            rows.map((row) => (
                                <tr key={row.key}>
                                    {columns.map((column) => (
                                        <td key={column.key} className={`px-4 py-3 ${column.className ?? ''}`}>
                                            {column.render(row)}
                                        </td>
                                    ))}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </CardBody>
            {paginationLinks && (
                <div className="px-4 py-3">
                    <Pagination links={paginationLinks} />
                </div>
            )}
        </Card>
    );
}

export default function Index({
    obligees,
    kycObligees,
    certificateObligeesFromKyc,
    certificateObligeesTyped,
    filters,
    kycView = false,
    branchConfirmationsView = false,
    branchName,
}) {
    const { can } = usePermission();
    const [deleteId, setDeleteId] = useState(null);
    const url = route('obligees.index');

    const { inputRef, isSearching, onInput, defaultSearch } = useDebouncedInertiaSearch({
        initialSearch: filters.search,
        url,
        buildParams: (searchValue) => ({
            search: searchValue.trim() || undefined,
        }),
        only: TABLE_PROPS,
    });

    if (branchConfirmationsView) {
        const certificateKycRows = (certificateObligeesFromKyc?.data ?? []).map((obligee) => ({
            key: `cert-kyc-${obligee.obligee_id}-${obligee.company_name}`,
            company_name: obligee.company_name,
            obligee_id: obligee.obligee_id,
            certificates_count: obligee.certificates_count,
        }));

        const certificateTypedRows = (certificateObligeesTyped?.data ?? []).map((obligee) => ({
            key: `cert-typed-${obligee.company_name}`,
            company_name: obligee.company_name,
            certificates_count: obligee.certificates_count,
        }));

        const branchLabel = branchName ? ` for ${branchName}` : ' for your branch';

        return (
            <AppLayout title="Obligees">
                <Head title="Obligees" />
                <p className="mb-4 text-sm text-slate-600">
                    Obligees used on confirmations generated{branchLabel}.
                </p>
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

                <ObligeeTable
                    title="Used on Confirmations — From KYC"
                    description="Obligees linked to a KYC record when the confirmation was generated."
                    columns={[
                        {
                            key: 'company_name',
                            label: 'Company',
                            render: (row) => <span className="font-medium">{row.company_name}</span>,
                        },
                        { key: 'obligee_id', label: 'KYC ID', render: (row) => row.obligee_id },
                        {
                            key: 'certificates_count',
                            label: 'Confirmations',
                            render: (row) => row.certificates_count,
                        },
                    ]}
                    rows={certificateKycRows}
                    emptyMessage="No confirmation obligees linked to KYC were found for your branch."
                    paginationLinks={certificateObligeesFromKyc?.links}
                />

                <ObligeeTable
                    title="Used on Confirmations — Typed In"
                    description="Obligee names entered manually on bond requests with generated confirmations."
                    columns={[
                        {
                            key: 'company_name',
                            label: 'Company',
                            render: (row) => <span className="font-medium">{row.company_name}</span>,
                        },
                        {
                            key: 'certificates_count',
                            label: 'Confirmations',
                            render: (row) => row.certificates_count,
                        },
                    ]}
                    rows={certificateTypedRows}
                    emptyMessage="No typed-in confirmation obligees were found for your branch."
                    paginationLinks={certificateObligeesTyped?.links}
                />
            </AppLayout>
        );
    }

    if (kycView) {
        const kycDirectoryRows = (kycObligees?.data ?? []).map((obligee) => ({
            key: `kyc-${obligee.id}`,
            company_name: obligee.company_name,
            contact_person: obligee.contact_person ?? '—',
            email: obligee.email ?? '—',
        }));

        const certificateKycRows = (certificateObligeesFromKyc?.data ?? []).map((obligee) => ({
            key: `cert-kyc-${obligee.obligee_id}-${obligee.company_name}`,
            company_name: obligee.company_name,
            obligee_id: obligee.obligee_id,
            certificates_count: obligee.certificates_count,
        }));

        const certificateTypedRows = (certificateObligeesTyped?.data ?? []).map((obligee) => ({
            key: `cert-typed-${obligee.company_name}`,
            company_name: obligee.company_name,
            certificates_count: obligee.certificates_count,
        }));

        return (
            <AppLayout title="Obligees">
                <Head title="Obligees" />
                <p className="mb-4 text-sm text-slate-600">
                    Review obligees from the KYC system and obligees that appear on generated confirmations.
                </p>
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

                <ObligeeTable
                    title="KYC Directory"
                    description="Obligees available in the KYC system."
                    columns={[
                        {
                            key: 'company_name',
                            label: 'Company',
                            render: (row) => <span className="font-medium">{row.company_name}</span>,
                        },
                        { key: 'contact_person', label: 'Contact', render: (row) => row.contact_person },
                        { key: 'email', label: 'Email', render: (row) => row.email },
                    ]}
                    rows={kycDirectoryRows}
                    emptyMessage="No KYC obligees found."
                    paginationLinks={kycObligees?.links}
                />

                <ObligeeTable
                    title="Used on Confirmations — From KYC"
                    description="Obligees linked to a KYC record when the confirmation was generated."
                    columns={[
                        {
                            key: 'company_name',
                            label: 'Company',
                            render: (row) => <span className="font-medium">{row.company_name}</span>,
                        },
                        { key: 'obligee_id', label: 'KYC ID', render: (row) => row.obligee_id },
                        {
                            key: 'certificates_count',
                            label: 'Confirmations',
                            render: (row) => row.certificates_count,
                        },
                    ]}
                    rows={certificateKycRows}
                    emptyMessage="No confirmation obligees linked to KYC were found."
                    paginationLinks={certificateObligeesFromKyc?.links}
                />

                <ObligeeTable
                    title="Used on Confirmations — Typed In"
                    description="Obligee names entered manually on bond requests with generated confirmations."
                    columns={[
                        {
                            key: 'company_name',
                            label: 'Company',
                            render: (row) => <span className="font-medium">{row.company_name}</span>,
                        },
                        {
                            key: 'certificates_count',
                            label: 'Confirmations',
                            render: (row) => row.certificates_count,
                        },
                    ]}
                    rows={certificateTypedRows}
                    emptyMessage="No typed-in confirmation obligees were found."
                    paginationLinks={certificateObligeesTyped?.links}
                />
            </AppLayout>
        );
    }

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
                            {obligees.data.length === 0 ? (
                                <tr>
                                    <td colSpan={4} className="px-4 py-8 text-center text-slate-500">
                                        No obligees found.
                                    </td>
                                </tr>
                            ) : (
                                obligees.data.map((o) => (
                                    <tr key={o.id}>
                                        <td className="px-4 py-3 font-medium">{o.company_name}</td>
                                        <td className="px-4 py-3">{o.contact_person}</td>
                                        <td className="px-4 py-3">{o.email}</td>
                                        <td className="space-x-2 px-4 py-3 text-right">
                                            <Link href={route('obligees.show', o.id)} className="text-sterling-green">View</Link>
                                            {can('obligees.update') && <Link href={route('obligees.edit', o.id)} className="text-slate-600">Edit</Link>}
                                            {can('obligees.delete') && <button type="button" onClick={() => setDeleteId(o.id)} className="text-red-600">Delete</button>}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </CardBody>
                <div className="px-4 py-3"><Pagination links={obligees.links} /></div>
            </Card>
            <ConfirmModal show={!!deleteId} onClose={() => setDeleteId(null)} onConfirm={() => router.delete(route('obligees.destroy', deleteId))} title="Delete Obligee" message="Remove this obligee record?" confirmLabel="Delete" danger />
        </AppLayout>
    );
}
