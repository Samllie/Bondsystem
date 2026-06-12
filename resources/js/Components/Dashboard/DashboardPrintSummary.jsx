export default function DashboardPrintSummary({ statRows, chartData, monthlyData = null }) {
    return (
        <div className="dashboard-print-summary print-only mt-6 hidden space-y-6 print:block">
            <div>
                <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-700">Statistics Summary</h2>
                <table className="dashboard-report-table mt-2 w-full">
                    <tbody>
                        {statRows.map((row) => (
                            <tr key={row.label}>
                                <td>{row.label}</td>
                                <td className="print-amount text-right font-semibold">{row.value}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {chartData.length > 0 && (
                <div>
                    <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-700">Bonds by Status</h2>
                    <table className="dashboard-report-table mt-2 w-full">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th className="print-amount">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            {chartData.map((row) => (
                                <tr key={row.status}>
                                    <td>{row.status}</td>
                                    <td className="print-amount text-right font-semibold">{row.count}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {monthlyData?.length > 0 && (
                <div>
                    <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-700">Monthly Requests</h2>
                    <table className="dashboard-report-table mt-2 w-full">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th className="print-amount">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            {monthlyData.map((row) => (
                                <tr key={row.month}>
                                    <td>{row.month}</td>
                                    <td className="print-amount text-right font-semibold">{row.count}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
