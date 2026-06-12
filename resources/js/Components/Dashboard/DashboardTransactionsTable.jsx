import Card, { CardBody, CardHeader } from '@/Components/UI/Card';
import Pagination from '@/Components/UI/Pagination';
import { Link } from '@inertiajs/react';

const php = (v) => Number(v).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });

export default function DashboardTransactionsTable({ transactionRecords }) {
    return (
        <Card className="dashboard-report-card mt-6">
            <CardHeader
                title="Transactions"
                action={
                    <Link href={route('payments.transactions.index')} className="no-print text-sm text-sterling-green hover:underline">
                        View all
                    </Link>
                }
            />
            <CardBody className="overflow-x-auto p-0">
                {transactionRecords.data.length === 0 ? (
                    <p className="px-6 py-8 text-center text-sm text-slate-500">No transactions match the current filters.</p>
                ) : (
                    <table className="dashboard-report-table min-w-full text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                {['Transaction #', 'Description', 'Type', 'Amount', 'Balance After', 'Date'].map((heading) => (
                                    <th
                                        key={heading}
                                        className={`px-4 py-3 text-left text-xs font-medium text-slate-500 ${
                                            heading === 'Amount' || heading === 'Balance After' ? 'print-amount' : ''
                                        }`}
                                    >
                                        {heading}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {transactionRecords.data.map((transaction) => (
                                <tr key={transaction.id} className="hover:bg-slate-50">
                                    <td className="px-4 py-3 font-mono text-xs font-medium text-sterling-green print:font-sans print:text-sm print:text-slate-900">
                                        {transaction.transaction_number}
                                    </td>
                                    <td className="px-4 py-3 text-slate-700">{transaction.description}</td>
                                    <td className="px-4 py-3 text-slate-600">{transaction.type_label}</td>
                                    <td
                                        className={`print-amount px-4 py-3 font-semibold ${
                                            transaction.type === 'credit' ? 'text-emerald-600' : 'text-red-500'
                                        } print:text-slate-900`}
                                    >
                                        {transaction.type === 'credit' ? '+' : '-'}
                                        {php(transaction.amount)}
                                    </td>
                                    <td className="print-amount px-4 py-3 text-slate-600">{php(transaction.balance_after)}</td>
                                    <td className="px-4 py-3 text-slate-500">{transaction.created_at}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </CardBody>
            {transactionRecords.links?.length > 3 && (
                <div className="no-print border-t border-slate-100 px-4 py-4">
                    <Pagination links={transactionRecords.links} />
                </div>
            )}
        </Card>
    );
}
