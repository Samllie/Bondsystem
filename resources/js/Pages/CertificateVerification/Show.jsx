import BackLink from '@/Components/UI/BackLink';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, usePage } from '@inertiajs/react';

function Detail({ label, value }) {
    return (
        <div>
            <dt className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</dt>
            <dd className="mt-1 text-sm text-slate-900">{value || '—'}</dd>
        </div>
    );
}

export default function Show({
    valid,
    status,
    confirmationNumber,
    certificateType,
    bondRequestReference,
    principal,
    obligee,
    amount,
    dateIssued,
    expiryDate,
    versionNumber,
    generatedDate,
    currentVersionNumber,
}) {
    const { auth } = usePage().props;
    const backHref = auth?.user ? route('dashboard') : route('login');
    const backLabel = auth?.user ? 'Back to Dashboard' : 'Back to Login';

    return (
        <GuestLayout unified>
            <Head title={valid ? 'Confirmation Verified' : 'Invalid Confirmation'} />

            <BackLink href={backHref} className="mb-0">
                {backLabel}
            </BackLink>

            {!valid ? (
                <div className="text-center">
                    <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-100 text-red-700">
                        <span className="text-2xl font-bold">!</span>
                    </div>
                    <h2 className="mt-4 font-serif text-xl font-bold text-red-700">
                        INVALID CONFIRMATION
                    </h2>
                    <p className="mt-2 text-sm text-slate-600">No confirmation found.</p>
                    <Link
                        href={route('certificate-verification.search')}
                        className="mt-6 inline-block text-sm font-medium text-sterling-green hover:underline"
                    >
                        Try another confirmation number
                    </Link>
                </div>
            ) : (
                <div>
                    <div className="text-center">
                        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                            <span className="text-2xl font-bold">✓</span>
                        </div>
                        <h2 className="mt-4 font-serif text-xl font-bold text-emerald-700">
                            {status === 'ARCHIVED' ? 'VALID CONFIRMATION VERSION' : 'VALID CONFIRMATION'}
                        </h2>
                        {status === 'ARCHIVED' && (
                            <p className="mt-2 text-sm text-slate-600">
                                This confirmation version has been superseded.
                            </p>
                        )}
                    </div>

                    <dl className="mt-6 grid gap-4 sm:grid-cols-2">
                        <Detail label="Confirmation Number" value={confirmationNumber} />
                        <Detail label="Confirmation Type" value={certificateType} />
                        <Detail label="Bond Request Reference" value={bondRequestReference} />
                        <Detail label="Principal" value={principal} />
                        <Detail label="Obligee" value={obligee} />
                        <Detail label="Amount" value={amount !== '—' ? `₱${amount}` : amount} />
                        <Detail label="Date Issued" value={dateIssued} />
                        <Detail label="Expiry Date" value={expiryDate} />
                        <Detail label="Version Number" value={versionNumber ? `V${versionNumber}` : '—'} />
                        <Detail label="Generated Date" value={generatedDate} />
                        <Detail label="Status" value={status} />
                        {status === 'ARCHIVED' && (
                            <Detail
                                label="Current Version"
                                value={currentVersionNumber ? `V${currentVersionNumber}` : '—'}
                            />
                        )}
                    </dl>

                    <div className="mt-6 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">
                        This page is for verification only. Confirmation files are not available for public download.
                    </div>

                    <Link
                        href={route('certificate-verification.search')}
                        className="mt-6 inline-block text-sm font-medium text-sterling-green hover:underline"
                    >
                        Verify another confirmation
                    </Link>
                </div>
            )}
        </GuestLayout>
    );
}
