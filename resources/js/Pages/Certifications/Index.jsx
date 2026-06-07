import Card, { CardBody } from '@/Components/UI/Card';
import Pagination from '@/Components/UI/Pagination';
import TableSearchInput from '@/Components/UI/TableSearchInput';
import useDebouncedInertiaSearch from '@/hooks/useDebouncedInertiaSearch';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';

const TABLE_PROPS = ['certificates', 'filters'];

export default function Index({ certificates, filters, isSuperAdmin, branchName }) {
    const url = route('certifications.index');

    const { inputRef, isSearching, onInput, defaultSearch } = useDebouncedInertiaSearch({
        initialSearch: filters.search,
        url,
        buildParams: (searchValue) => ({ search: searchValue.trim() || undefined }),
        only: TABLE_PROPS,
    });

    return (
        <AppLayout title="Certifications">
            <Head title="Certifications" />

            <Card className="mb-4">
                <CardBody>
                    <p className="mb-3 text-sm text-slate-600">
                        {isSuperAdmin
                            ? 'Showing generated certificates across all branches.'
                            : `Showing generated certificates for your branch${branchName ? ` (${branchName})` : ''}.`}
                    </p>
                    <TableSearchInput
                        inputRef={inputRef}
                        defaultSearch={defaultSearch}
                        onInput={onInput}
                        isSearching={isSearching}
                        placeholder="Search bond #, CAR #, obligee, principal..."
                        wrapperClassName="relative w-full"
                        className="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-sterling-gold focus:ring-sterling-gold"
                    />
                </CardBody>
            </Card>

            <Card>
                <CardBody className="overflow-x-auto p-0">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium text-slate-600">Bond / CAR #</th>
                                <th className="px-4 py-3 text-left font-medium text-slate-600">Type</th>
                                <th className="px-4 py-3 text-left font-medium text-slate-600">Obligee</th>
                                <th className="px-4 py-3 text-left font-medium text-slate-600">Principal</th>
                                <th className="px-4 py-3 text-left font-medium text-slate-600">Branch</th>
                                <th className="px-4 py-3 text-left font-medium text-slate-600">Date</th>
                                <th className="px-4 py-3 text-right font-medium text-slate-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {certificates.data.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-4 py-10 text-center text-slate-500">
                                        No certificates found.
                                    </td>
                                </tr>
                            )}
                            {certificates.data.map((cert) => (
                                <tr key={cert.id} className="hover:bg-slate-50">
                                    <td className="px-4 py-3 font-medium">{cert.bond_label || cert.bond_number}</td>
                                    <td className="px-4 py-3">{cert.certificate_type_label}</td>
                                    <td className="px-4 py-3">{cert.obligee_name || '—'}</td>
                                    <td className="px-4 py-3">{cert.principal_name || '—'}</td>
                                    <td className="px-4 py-3">{cert.branch_name}</td>
                                    <td className="px-4 py-3">{cert.request_date || '—'}</td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-3">
                                            <a
                                                href={route('bond-requests.view-certificate', cert.id)}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="text-sterling-green hover:underline"
                                            >
                                                View
                                            </a>
                                            <a
                                                href={route('bond-requests.download-certificate', cert.id)}
                                                className="text-sterling-green hover:underline"
                                            >
                                                Download
                                            </a>
                                            {cert.has_docx && (
                                                <a
                                                    href={route('bond-requests.download-docx', cert.id)}
                                                    className="text-slate-500 hover:underline"
                                                >
                                                    DOCX
                                                </a>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </CardBody>
                <div className="border-t border-slate-100 px-4 py-3">
                    <Pagination links={certificates.links} />
                </div>
            </Card>
        </AppLayout>
    );
}
