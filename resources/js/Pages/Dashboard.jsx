import PrimaryButton from '@/Components/PrimaryButton';
import DashboardBondsTable from '@/Components/Dashboard/DashboardBondsTable';
import DashboardFilters from '@/Components/Dashboard/DashboardFilters';
import DashboardPrintHeader from '@/Components/Dashboard/DashboardPrintHeader';
import DashboardPrintSummary from '@/Components/Dashboard/DashboardPrintSummary';
import DashboardTransactionsTable from '@/Components/Dashboard/DashboardTransactionsTable';
import DashboardViewToggle from '@/Components/Dashboard/DashboardViewToggle';
import Card, { CardBody, CardHeader } from '@/Components/UI/Card';
import StatCard from '@/Components/UI/StatCard';
import StatusBadge from '@/Components/UI/StatusBadge';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

const php = (v) => Number(v).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });

export default function Dashboard({
    stats,
    recentRequests,
    recentTransactions,
    chartData,
    bondRecords,
    transactionRecords,
    filters,
    statusOptions,
    bondTypeOptions,
    filterSummary,
    generatedAt,
}) {
    const branchFundLabel = stats.branch_name ? `${stats.branch_name} Fund` : 'Branch Fund';
    const isTableView = filters.view === 'table';

    const statRows = [
        { label: 'My Bonds', value: stats.my_bonds },
        { label: 'Pending', value: stats.pending },
        { label: 'Approved', value: stats.approved },
        { label: 'Notarized', value: stats.notarized },
        { label: 'Pending Deposits', value: stats.pending_deposits },
        { label: branchFundLabel, value: php(stats.balance) },
    ];

    return (
        <AppLayout
            title="My Dashboard"
            actions={
                <>
                    <button type="button" onClick={() => window.print()} className="btn-secondary no-print">
                        Print Report
                    </button>
                    <Link href={route('payments.deposits.create')} className="no-print">
                        <PrimaryButton>+ Deposit</PrimaryButton>
                    </Link>
                </>
            }
        >
            <Head title="Dashboard" />

            <DashboardPrintHeader
                title="My Dashboard Report"
                filterSummary={filterSummary}
                generatedAt={generatedAt}
            />

            <DashboardFilters
                filters={filters}
                statusOptions={statusOptions}
                bondTypeOptions={bondTypeOptions}
            />

            <DashboardViewToggle filters={filters} />

            <div className="dashboard-print-content">
                <div className="print-overview-hidden grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <StatCard label="My Bonds" value={stats.my_bonds} color="blue" />
                    <StatCard label="Pending" value={stats.pending} color="amber" />
                    <StatCard label="Approved" value={stats.approved} color="blue" />
                    <StatCard label="Notarized" value={stats.notarized} color="green" />
                    <StatCard label="Pending Deposits" value={stats.pending_deposits} color="amber" />
                    <div className="rounded-xl border-2 border-sterling-gold/40 bg-sterling-gold-50 p-5 shadow-sm">
                        <p className="text-sm font-medium text-sterling-green">{branchFundLabel}</p>
                        <p className="mt-2 text-3xl font-bold text-sterling-green-darker">{php(stats.balance)}</p>
                    </div>
                </div>

                {isTableView ? (
                    <>
                        <div className="mt-6">
                            <DashboardBondsTable bondRecords={bondRecords} variant="requester" />
                        </div>
                        <DashboardTransactionsTable transactionRecords={transactionRecords} />
                    </>
                ) : (
                    <div className="mt-6 grid gap-6 lg:grid-cols-2">
                        <Card className="dashboard-report-card">
                            <CardHeader
                                title="My Bond Requests"
                                action={
                                    <Link
                                        href={route('bond-requests.index')}
                                        className="no-print text-sm text-sterling-green hover:underline"
                                    >
                                        View all
                                    </Link>
                                }
                            />
                            <CardBody className="overflow-x-auto p-0">
                                <table className="dashboard-report-table min-w-full text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            {['Bond #', 'Amount', 'Status', 'Date', 'Confirmation'].map((h) => (
                                                <th
                                                    key={h}
                                                    className={`px-4 py-3 text-left text-xs font-medium text-slate-500 ${
                                                        h === 'Confirmation' ? 'print-hide-certificate-col' : ''
                                                    } ${h === 'Amount' ? 'print-amount' : ''}`}
                                                >
                                                    {h}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {recentRequests.map((r) => (
                                            <tr key={r.id} className="hover:bg-slate-50">
                                                <td className="px-4 py-3">
                                                    <Link
                                                        href={route('bond-requests.show', r.id)}
                                                        className="font-medium text-sterling-green hover:underline print:text-slate-900 print:no-underline"
                                                    >
                                                        {r.bond_number}
                                                    </Link>
                                                </td>
                                                <td className="print-amount px-4 py-3">{php(r.amount)}</td>
                                                <td className="px-4 py-3">
                                                    <StatusBadge label={r.status_label} color={r.status_color} />
                                                </td>
                                                <td className="px-4 py-3 text-slate-500">{r.request_date}</td>
                                                <td className="print-hide-certificate-col px-4 py-3">
                                                    <span className="no-print">
                                                        {r.has_certificate ? (
                                                            <div className="flex flex-wrap gap-2">
                                                                <a
                                                                    href={route('bond-requests.view-certificate', r.id)}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    className="text-xs font-medium text-sterling-green hover:underline"
                                                                >
                                                                    View
                                                                </a>
                                                                <span className="text-slate-300">|</span>
                                                                <a
                                                                    href={route('bond-requests.download-certificate', r.id)}
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
                                                        {r.has_certificate ? 'Yes' : 'No'}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </CardBody>
                        </Card>

                        <div className="print-overview-hidden flex flex-col gap-6">
                            <Card>
                                <CardHeader title="My Bonds by Status" />
                                <CardBody>
                                    <div className="h-44">
                                        <ResponsiveContainer width="100%" height="100%">
                                            <PieChart>
                                                <Pie
                                                    data={chartData}
                                                    dataKey="count"
                                                    nameKey="status"
                                                    cx="50%"
                                                    cy="50%"
                                                    outerRadius={65}
                                                    label
                                                >
                                                    {chartData.map((e, i) => (
                                                        <Cell key={i} fill={e.color} />
                                                    ))}
                                                </Pie>
                                                <Tooltip />
                                            </PieChart>
                                        </ResponsiveContainer>
                                    </div>
                                </CardBody>
                            </Card>

                            <Card>
                                <CardHeader
                                    title="Recent Transactions"
                                    action={
                                        <Link
                                            href={route('payments.transactions.index')}
                                            className="no-print text-sm text-sterling-green hover:underline"
                                        >
                                            View all
                                        </Link>
                                    }
                                />
                                <CardBody>
                                    {recentTransactions.length === 0 ? (
                                        <p className="text-sm text-slate-500">No transactions yet.</p>
                                    ) : (
                                        <ul className="space-y-2">
                                            {recentTransactions.map((t) => (
                                                <li
                                                    key={t.id}
                                                    className="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2 text-sm"
                                                >
                                                    <div>
                                                        <p className="font-mono text-xs font-medium text-sterling-green">
                                                            {t.transaction_number}
                                                        </p>
                                                        <p className="font-medium text-slate-800">{t.description}</p>
                                                        <p className="text-xs text-slate-500">{t.created_at}</p>
                                                    </div>
                                                    <span
                                                        className={`font-semibold ${t.type === 'credit' ? 'text-emerald-600' : 'text-red-500'}`}
                                                    >
                                                        {t.type === 'credit' ? '+' : '-'}
                                                        {php(t.amount)}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </CardBody>
                            </Card>
                        </div>
                    </div>
                )}

                <DashboardPrintSummary statRows={statRows} chartData={chartData} />
            </div>
        </AppLayout>
    );
}
