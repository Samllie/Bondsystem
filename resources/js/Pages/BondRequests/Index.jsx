import PrimaryButton from '@/Components/PrimaryButton';
import Card, { CardBody } from '@/Components/UI/Card';
import Pagination from '@/Components/UI/Pagination';
import StatusBadge from '@/Components/UI/StatusBadge';
import TableSearchInput from '@/Components/UI/TableSearchInput';
import useDebouncedInertiaSearch from '@/hooks/useDebouncedInertiaSearch';
import { visitTable } from '@/lib/visitTable';
import { usePermission } from '@/hooks/usePermission';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const TABLE_PROPS = ['bondRequests', 'filters'];

export default function Index({ bondRequests, filters, statusOptions, bondTypeOptions }) {
    const { can } = usePermission();
    const [status, setStatus] = useState(filters.status || '');
    const [bondTypeId, setBondTypeId] = useState(filters.bond_type_id || '');
    const url = route('bond-requests.index');

    useEffect(() => {
        setStatus(filters.status || '');
        setBondTypeId(filters.bond_type_id || '');
    }, [filters.status, filters.bond_type_id]);

    const buildParams = (searchValue) => ({
        search: searchValue.trim() || undefined,
        status: status || undefined,
        bond_type_id: bondTypeId || undefined,
    });

    const { inputRef, isSearching, onInput, getValue, defaultSearch } = useDebouncedInertiaSearch({
        initialSearch: filters.search,
        url,
        buildParams,
        only: TABLE_PROPS,
    });

    const handleStatusChange = (e) => {
        const value = e.target.value;
        setStatus(value);
        visitTable(url, {
            search: getValue().trim() || undefined,
            status: value || undefined,
            bond_type_id: bondTypeId || undefined,
        }, TABLE_PROPS, { inputRef });
    };

    const handleBondTypeChange = (e) => {
        const value = e.target.value;
        setBondTypeId(value);
        visitTable(url, {
            search: getValue().trim() || undefined,
            status: status || undefined,
            bond_type_id: value || undefined,
        }, TABLE_PROPS, { inputRef });
    };

    return (
        <AppLayout
            title="Bond Requests"
            actions={
                can('bond-requests.create') && (
                    <Link href={route('bond-requests.create')}>
                        <PrimaryButton>New Bond Request</PrimaryButton>
                    </Link>
                )
            }
        >
            <Head title="Bond Requests" />

            <Card className="mb-4">
                <CardBody>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <TableSearchInput
                            inputRef={inputRef}
                            defaultSearch={defaultSearch}
                            onInput={onInput}
                            isSearching={isSearching}
                            placeholder="Search bond #, principal, obligee..."
                            wrapperClassName="relative w-full sm:col-span-3"
                            className="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-sterling-gold focus:ring-sterling-gold"
                        />
                        <select value={status} onChange={handleStatusChange} className="rounded-md border-slate-300 text-sm">
                            <option value="">All statuses</option>
                            {statusOptions.map((o) => (
                                <option key={o.value} value={o.value}>{o.label}</option>
                            ))}
                        </select>
                        <select value={bondTypeId} onChange={handleBondTypeChange} className="rounded-md border-slate-300 text-sm sm:col-span-2">
                            <option value="">All types</option>
                            {bondTypeOptions.map((o) => (
                                <option key={o.value} value={o.value}>{o.label}</option>
                            ))}
                        </select>
                    </div>
                </CardBody>
            </Card>

            <Card>
                <CardBody className="overflow-x-auto p-0">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium text-slate-600">Bond #</th>
                                <th className="px-4 py-3 text-left font-medium text-slate-600">Type</th>
                                <th className="px-4 py-3 text-left font-medium text-slate-600">Principal</th>
                                <th className="px-4 py-3 text-left font-medium text-slate-600">Obligee</th>
                                <th className="px-4 py-3 text-left font-medium text-slate-600">Amount</th>
                                <th className="px-4 py-3 text-left font-medium text-slate-600">Status</th>
                                <th className="px-4 py-3 text-right font-medium text-slate-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {bondRequests.data.map((bond) => (
                                <tr key={bond.id} className="hover:bg-slate-50">
                                    <td className="px-4 py-3 font-medium">{bond.bond_number}</td>
                                    <td className="px-4 py-3 capitalize">{bond.bond_type}</td>
                                    <td className="px-4 py-3">{bond.principal?.company_name}</td>
                                    <td className="px-4 py-3">{bond.obligee_name ?? bond.obligee?.company_name}</td>
                                    <td className="px-4 py-3">
                                        {Number(bond.amount).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' })}
                                    </td>
                                    <td className="px-4 py-3">
                                        <StatusBadge
                                            label={statusOptions.find((s) => s.value === (bond.status?.value || bond.status))?.label || bond.status}
                                            color={statusOptions.find((s) => s.value === (bond.status?.value || bond.status))?.color || 'gray'}
                                        />
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Link href={route('bond-requests.show', bond.id)} className="text-sterling-green hover:underline">
                                            View
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </CardBody>
                <div className="border-t border-slate-100 px-4 py-3">
                    <Pagination links={bondRequests.links} />
                </div>
            </Card>
        </AppLayout>
    );
}
