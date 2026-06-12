export default function ReportPrintHeader({ title, filterSummary = [], generatedAt }) {
    const printedAt =
        generatedAt ??
        new Date().toLocaleString('en-PH', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });

    return (
        <div className="print-only mb-6 hidden border-b border-slate-300 pb-4 print:block">
            <p className="text-xs uppercase tracking-wide text-slate-500">Sterling Insurance Company, Inc.</p>
            <h1 className="mt-1 font-serif text-2xl font-bold text-slate-900">{title}</h1>
            <p className="mt-2 text-sm text-slate-600">Generated on {printedAt}</p>
            <p className="mt-1 text-sm text-slate-600">
                {filterSummary.length > 0
                    ? `Filters: ${filterSummary.join(' · ')}`
                    : 'Filters: All records'}
            </p>
        </div>
    );
}
