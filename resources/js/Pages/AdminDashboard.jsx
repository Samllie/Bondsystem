import DashboardBondsTable from '@/Components/Dashboard/DashboardBondsTable';
import DashboardFilters from '@/Components/Dashboard/DashboardFilters';
import DashboardPrintHeader from '@/Components/Dashboard/DashboardPrintHeader';
import DashboardPrintSummary from '@/Components/Dashboard/DashboardPrintSummary';
import DashboardViewToggle from '@/Components/Dashboard/DashboardViewToggle';
import Card, { CardBody, CardHeader } from '@/Components/UI/Card';
import StatCard from '@/Components/UI/StatCard';
import StatusBadge from '@/Components/UI/StatusBadge';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { Bar, BarChart, Cell, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

const php = (v) => Number(v).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });

export default function AdminDashboard({
    stats,
    recentRequests,
    pendingDeposits,
    chartData,
    monthlyData,
    activityFeed,
    bondRecords,
    filters,
    statusOptions,
    bondTypeOptions,
    branchOptions,
    showBranchFilter,
    filterSummary,
    generatedAt,
}) {
    const isTableView = filters.view === 'table';

    const statRows = [
        { label: 'Total Bonds', value: stats.total_bonds },
        { label: 'Pending Approval', value: stats.pending_approval },
        { label: 'Approved', value: stats.approved },
        { label: 'Notarized', value: stats.notarized },
        { label: 'Active Users', value: stats.total_users },
        { label: 'Pending Deposits', value: stats.pending_deposits },
        { label: 'Obligees', value: stats.total_obligees },
        { label: 'Principals', value: stats.total_principals },
    ];

    return (
        <AppLayout
            title="Admin Dashboard"
            actions={
                <button type="button" onClick={() => window.print()} className="btn-secondary no-print">
                    Print Report
                </button>
            }
        >
            <Head title="Admin Dashboard" />

            <DashboardPrintHeader
                title="Admin Dashboard Report"
                filterSummary={filterSummary}
                generatedAt={generatedAt}
            />

            <DashboardFilters
                filters={filters}
                statusOptions={statusOptions}
                bondTypeOptions={bondTypeOptions}
                branchOptions={branchOptions}
                showBranchFilter={showBranchFilter}
            />

            <DashboardViewToggle filters={filters} />

            <div className="dashboard-print-content">
                <div className="print-overview-hidden grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Total Bonds" value={stats.total_bonds} color="blue" />
                    <StatCard label="Pending Approval" value={stats.pending_approval} color="amber" />
                    <StatCard label="Approved" value={stats.approved} color="blue" />
                    <StatCard label="Notarized" value={stats.notarized} color="green" />
                    <StatCard label="Active Users" value={stats.total_users} color="slate" />
                    <StatCard label="Pending Deposits" value={stats.pending_deposits} color="amber" />
                    <StatCard label="Obligees" value={stats.total_obligees} color="slate" />
                    <StatCard label="Principals" value={stats.total_principals} color="slate" />
                </div>

                {isTableView ? (
                    <div className="mt-6">
                        <DashboardBondsTable bondRecords={bondRecords} variant="admin" />
                    </div>
                ) : (
                    <>
                        <div className="print-overview-hidden mt-6 grid gap-6 lg:grid-cols-2">
                            <Card>
                                <CardHeader title="Bonds by Status" />
                                <CardBody>
                                    <div className="h-56">
                                        <ResponsiveContainer width="100%" height="100%">
                                            <PieChart>
                                                <Pie
                                                    data={chartData}
                                                    dataKey="count"
                                                    nameKey="status"
                                                    cx="50%"
                                                    cy="50%"
                                                    outerRadius={80}
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
                                <CardHeader title="Monthly Requests (6 months)" />
                                <CardBody>
                                    <div className="h-56">
                                        <ResponsiveContainer width="100%" height="100%">
                                            <BarChart data={monthlyData}>
                                                <XAxis dataKey="month" tick={{ fontSize: 11 }} />
                                                <YAxis allowDecimals={false} />
                                                <Tooltip />
                                                <Bar dataKey="count" fill="#1A6333" radius={[4, 4, 0, 0]} />
                                            </BarChart>
                                        </ResponsiveContainer>
                                    </div>
                                </CardBody>
                            </Card>
                        </div>

                        <div className="mt-6 grid gap-6 xl:grid-cols-3">
                            <Card className="dashboard-report-card xl:col-span-2">
                                <CardHeader
                                    title="Recent Bond Requests"
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
                                                {['Bond #', 'Principal', 'Amount', 'Status'].map((h) => (
                                                    <th
                                                        key={h}
                                                        className={`px-4 py-3 text-left text-xs font-medium text-slate-500 ${
                                                            h === 'Amount' ? 'print-amount' : ''
                                                        }`}
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
                                                    <td className="px-4 py-3 text-slate-600">{r.principal}</td>
                                                    <td className="print-amount px-4 py-3 text-slate-600">{php(r.amount)}</td>
                                                    <td className="px-4 py-3">
                                                        <StatusBadge label={r.status_label} color={r.status_color} />
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </CardBody>
                            </Card>

                            <Card className="print-overview-hidden">
                                <CardHeader
                                    title="Pending Deposits"
                                    action={
                                        <Link
                                            href={route('payments.deposits.index')}
                                            className="no-print text-sm text-sterling-green hover:underline"
                                        >
                                            View all
                                        </Link>
                                    }
                                />
                                <CardBody>
                                    {pendingDeposits.length === 0 ? (
                                        <p className="text-sm text-slate-500">No pending deposits.</p>
                                    ) : (
                                        <ul className="space-y-3">
                                            {pendingDeposits.map((d) => (
                                                <li key={d.id} className="rounded-lg border border-slate-100 p-3">
                                                    <p className="font-medium text-slate-900">{d.user}</p>
                                                    <p className="text-xs text-slate-500">
                                                        {d.bank} · {d.deposit_date}
                                                    </p>
                                                    <p className="mt-1 text-sm font-semibold text-emerald-700">{php(d.amount)}</p>
                                                    <Link
                                                        href={route('payments.deposits.show', d.id)}
                                                        className="no-print mt-1 inline-block text-xs text-sterling-green hover:underline"
                                                    >
                                                        Review →
                                                    </Link>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </CardBody>
                            </Card>
                        </div>

                        <Card className="mt-6 no-print">
                            <CardHeader title="Activity Feed" />
                            <CardBody>
                                <ul className="space-y-3">
                                    {activityFeed.map((log) => (
                                        <li key={log.id} className="flex gap-3 border-b border-slate-50 pb-3 last:border-0">
                                            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600">
                                                {log.user.charAt(0)}
                                            </div>
                                            <div>
                                                <p className="text-sm text-slate-800">{log.description}</p>
                                                <p className="text-xs text-slate-500">
                                                    {log.user} · {log.created_at}
                                                </p>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </CardBody>
                        </Card>
                    </>
                )}

                <DashboardPrintSummary statRows={statRows} chartData={chartData} monthlyData={monthlyData} />
            </div>
        </AppLayout>
    );
}
