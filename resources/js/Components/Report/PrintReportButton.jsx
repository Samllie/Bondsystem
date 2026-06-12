export default function PrintReportButton({ label = 'Print Report' }) {
    return (
        <button type="button" onClick={() => window.print()} className="btn-secondary no-print">
            {label}
        </button>
    );
}
