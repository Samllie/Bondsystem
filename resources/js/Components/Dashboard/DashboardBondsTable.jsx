import Card, { CardBody, CardHeader } from '@/Components/UI/Card';
import Pagination from '@/Components/UI/Pagination';
import StatusBadge from '@/Components/UI/StatusBadge';
import { Link } from '@inertiajs/react';

const php = (v) => Number(v).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });

export default function DashboardBondsTable({ bondRecords, variant = 'requester' }) {
    const isAdmin = variant === 'admin';

    const headings = isAdmin
        ? ['Bond #', 'Type', 'Principal', 'Obligee', 'Requester', 'Branch', 'Amount', 'Status', 'Date']
        : ['Bond #', 'Type', 'Principal', 'Amount', 'Status', 'Date', 'Confirmation'];

    return (
        <Card className="dashboard-report-card">
            <CardHeader
                title={isAdmin ? 'Bond Requests' : 'My Bond Requests'}
                action={
                    <span className="no-print text-xs text-slate-500">
                        {bondRecords.total} record{bondRecords.total === 1 ? '' : 's'}
                    </span>
                }
            />
            <CardBody className="overflow-x-auto p-0">
                {bondRecords.data.length === 0 ? (
                    <p className="px-6 py-8 text-center text-sm text-slate-500">No bond requests match the current filters.</p>
                ) : (
                    <table className="dashboard-report-table min-w-full text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                {headings.map((heading) => (
                                    <th
                                        key={heading}
                                        className={`px-4 py-3 text-left text-xs font-medium text-slate-500 ${
                                            heading === 'Confirmation' ? 'print-hide-certificate-col' : ''
                                        } ${heading === 'Amount' ? 'print-amount' : ''}`}
                                    >
                                        {heading}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {bondRecords.data.map((record) => (
                                <tr key={record.id} className="hover:bg-slate-50">
                                    <td className="px-4 py-3">
                                        <Link
                                            href={route('bond-requests.show', record.id)}
                                            className="font-medium text-sterling-green hover:underline print:text-slate-900 print:no-underline"
                                        >
                                            {record.bond_number}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">{record.bond_type}</td>
                                    <td className="px-4 py-3 text-slate-600">{record.principal ?? '—'}</td>
                                    {isAdmin && (
                                        <>
                                            <td className="px-4 py-3 text-slate-600">{record.obligee ?? '—'}</td>
                                            <td className="px-4 py-3 text-slate-600">{record.requester ?? '—'}</td>
                                            <td className="px-4 py-3 text-slate-600">{record.branch ?? '—'}</td>
                                        </>
                                    )}
                                    <td className="print-amount px-4 py-3 text-slate-600">{php(record.amount)}</td>
                                    <td className="px-4 py-3">
                                        <StatusBadge label={record.status_label} color={record.status_color} />
                                    </td>
                                    <td className="px-4 py-3 text-slate-500">{record.request_date}</td>
                                    {!isAdmin && (
                                        <td className="print-hide-certificate-col px-4 py-3">
                                            <span className="no-print">
                                                {record.has_certificate ? (
                                                    <div className="flex flex-wrap gap-2">
                                                        <a
                                                            href={route('bond-requests.view-certificate', record.id)}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="text-xs font-medium text-sterling-green hover:underline"
                                                        >
                                                            View
                                                        </a>
                                                        <span className="text-slate-300">|</span>
                                                        <a
                                                            href={route('bond-requests.download-certificate', record.id)}
                                                            className="text-xs font-medium text-sterling-green hover:underline"
                                                        >
                                                            Download
                                                        </a>
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-slate-400">Not yet available</span>
                                                )}
                                            </span>
                                            <span className="print-only hidden print:inline">
                                                {record.has_certificate ? 'Yes' : 'No'}
                                            </span>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </CardBody>
            {bondRecords.links?.length > 3 && (
                <div className="no-print border-t border-slate-100 px-4 py-4">
                    <Pagination links={bondRecords.links} />
                </div>
            )}
        </Card>
    );
}
