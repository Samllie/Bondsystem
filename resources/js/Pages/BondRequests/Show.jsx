import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import Card, { CardBody, CardHeader } from '@/Components/UI/Card';
import ConfirmModal from '@/Components/UI/ConfirmModal';
import { SelectField, TextField } from '@/Components/UI/FormField';
import StatusBadge from '@/Components/UI/StatusBadge';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateInWords } from '@/lib/formatDateInWords';
import { formatBookNoDisplay, formatBookNoInput } from '@/lib/romanNumerals';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

export default function Show({
    bondRequest,
    supportingDocumentUrl,
    canUpdate,
    canDelete,
    canApprove,
    canNotarize,
    signatoryOptions,
    notaryOptions,
}) {
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [bookNoDraft, setBookNoDraft] = useState('');
    const bookNoDebounceRef = useRef(null);
    const status = bondRequest.status?.value || bondRequest.status;

    const approveForm = useForm({
        signatory_id: '',
        notary_id: '',
        doc_no: '',
        page_no: '',
        book_no: '',
        series_year: bondRequest.series_year || String(new Date().getFullYear()),
    });

    const signatorySelectOptions = useMemo(
        () => [{ value: '', label: 'Select signatory…' }, ...signatoryOptions],
        [signatoryOptions],
    );

    const notarySelectOptions = useMemo(
        () => [{ value: '', label: 'Select notary…' }, ...notaryOptions],
        [notaryOptions],
    );

    useEffect(() => () => clearTimeout(bookNoDebounceRef.current), []);

    const handleSignatoryChange = (event) => {
        approveForm.setData('signatory_id', event.target.value);
    };

    const handleBookNoChange = (event) => {
        const value = event.target.value;
        setBookNoDraft(value);

        clearTimeout(bookNoDebounceRef.current);
        bookNoDebounceRef.current = setTimeout(() => {
            const formatted = formatBookNoInput(value);
            setBookNoDraft(formatted);
            approveForm.setData('book_no', formatted);
        }, 750);
    };

    const submitApprove = (e) => {
        e.preventDefault();

        clearTimeout(bookNoDebounceRef.current);
        const formattedBookNo = formatBookNoInput(bookNoDraft);
        setBookNoDraft(formattedBookNo);

        approveForm.transform((current) => ({
            ...current,
            book_no: formattedBookNo,
        }));

        approveForm.post(route('bond-requests.approve', bondRequest.id), {
            preserveScroll: true,
        });
    };

    const addresses = [bondRequest.address_1, bondRequest.address_2, bondRequest.address_3].filter(Boolean);
    const inceptionDate = bondRequest.inception_date ? String(bondRequest.inception_date).substring(0, 10) : null;
    const hasCertificateDetails = bondRequest.signatory_id || bondRequest.notary_id || bondRequest.doc_no;

    return (
        <AppLayout
            title={`Bond ${bondRequest.bond_number}`}
            actions={
                <div className="flex flex-wrap gap-2">
                    {canUpdate && (
                        <Link href={route('bond-requests.edit', bondRequest.id)}>
                            <SecondaryButton>Edit</SecondaryButton>
                        </Link>
                    )}
                    {canApprove && (
                        <SecondaryButton type="button" onClick={() => router.post(route('bond-requests.reject', bondRequest.id))}>
                            Reject
                        </SecondaryButton>
                    )}
                    {canNotarize && (
                        <PrimaryButton
                            type="button"
                            onClick={() => router.post(route('bond-requests.notarize', bondRequest.id))}
                        >
                            Mark Notarized
                        </PrimaryButton>
                    )}
                    {canDelete && (
                        <SecondaryButton onClick={() => setDeleteOpen(true)} className="!text-red-600">
                            Delete
                        </SecondaryButton>
                    )}
                </div>
            }
        >
            <Head title={bondRequest.bond_number} />

            <Card>
                <CardHeader
                    title="Bond Details"
                    action={<StatusBadge label={bondRequest.status_label || status} color={bondRequest.status_color || 'gray'} />}
                />
                <CardBody>
                    <dl className="grid gap-4 sm:grid-cols-2">
                        {(bondRequest.certificate_type?.value || bondRequest.certificate_type) === 'car_certificate' ? (
                            <>
                                <Detail label="CAR" value={bondRequest.car || bondRequest.bond_label || '—'} className="sm:col-span-2" capitalize={false} />
                                <Detail label="Authorized Representative" value={bondRequest.authorized_representative || '—'} className="sm:col-span-2" capitalize={false} />
                                <Detail label="TIN" value={bondRequest.tin || '—'} capitalize={false} />
                            </>
                        ) : (
                            <>
                                <Detail label="Bond Number" value={bondRequest.bondTypeMaster?.code ?? bondRequest.bond_number} />
                                <Detail label="Bond" value={bondRequest.bond_label || '—'} className="sm:col-span-2" capitalize={false} />
                                <Detail label="Bond Type" value={bondRequest.bond_type_label} />
                            </>
                        )}
                        <Detail label="Principal" value={bondRequest.principal?.company_name} />
                        <Detail label="Obligee" value={bondRequest.obligee?.company_name} />
                        <Detail
                            label="Address"
                            value={addresses.length ? addresses.join(', ') : '—'}
                            className="sm:col-span-2"
                            capitalize={false}
                        />
                        <Detail label="Request Date" value={bondRequest.request_date} />
                        <Detail label="Date issued" value={bondRequest.date_issued || '—'} />
                        <Detail label="Inception date" value={inceptionDate || '—'} />
                        <Detail
                            label="Inception date in words"
                            value={inceptionDate ? formatDateInWords(inceptionDate) : '—'}
                            className="sm:col-span-2"
                            capitalize={false}
                        />
                        <Detail label="Attention" value={bondRequest.attention || '—'} capitalize={false} />
                        <Detail label="Certificate type" value={bondRequest.certificate_type_label || '—'} />
                        <Detail
                            label="Expiry date or validity statement"
                            value={bondRequest.expiry_date || '—'}
                            className="sm:col-span-2"
                            capitalize={false}
                        />
                        <Detail
                            label="Amount"
                            value={Number(bondRequest.amount).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' })}
                        />
                        <Detail label="Amount in words" value={bondRequest.amount_in_words || '—'} className="sm:col-span-2" capitalize={false} />
                        <Detail label="Project name" value={bondRequest.project_name || '—'} className="sm:col-span-2" />
                        {supportingDocumentUrl && (
                            <Detail
                                label="Supporting document"
                                value={
                                    <a href={supportingDocumentUrl} target="_blank" rel="noopener noreferrer" className="text-sterling-green hover:underline">
                                        View document →
                                    </a>
                                }
                                className="sm:col-span-2"
                                capitalize={false}
                            />
                        )}
                        {hasCertificateDetails && (
                            <>
                                <Detail label="Signatory" value={bondRequest.signatory?.name || '—'} />
                                <Detail label="Position" value={bondRequest.signatory_position || bondRequest.signatory?.position || '—'} />
                                <Detail label="Notary" value={bondRequest.notary?.name || '—'} />
                                <Detail label="Doc No." value={bondRequest.doc_no || '—'} />
                                <Detail label="Page No." value={bondRequest.page_no || '—'} />
                                <Detail label="Book No." value={formatBookNoDisplay(bondRequest.book_no) || '—'} />
                                <Detail label="Series year" value={bondRequest.series_year || '—'} />
                            </>
                        )}
                        <Detail label="Created By" value={bondRequest.creator?.name} />
                    </dl>
                </CardBody>
            </Card>

            {canApprove && (
                <Card className="mt-6">
                    <CardHeader title="Approver review" />
                    <CardBody>
                        <p className="mb-4 text-sm text-slate-600">
                            Complete the certificate details below, then approve the request.
                        </p>
                        <form onSubmit={submitApprove} className="grid gap-4 sm:grid-cols-2">
                            <SelectField
                                label="Signatory"
                                value={approveForm.data.signatory_id}
                                onChange={handleSignatoryChange}
                                options={signatorySelectOptions}
                                error={approveForm.errors.signatory_id}
                                required
                            />
                            <TextField
                                label="Position"
                                value={
                                    signatoryOptions.find(
                                        (option) => String(option.value) === String(approveForm.data.signatory_id),
                                    )?.position || '—'
                                }
                                readOnly
                                className="bg-slate-50"
                            />
                            <SelectField
                                label="Notary"
                                value={approveForm.data.notary_id}
                                onChange={(e) => approveForm.setData('notary_id', e.target.value)}
                                options={notarySelectOptions}
                                error={approveForm.errors.notary_id}
                                required
                            />
                            <TextField
                                label="Doc No."
                                value={approveForm.data.doc_no}
                                onChange={(e) => approveForm.setData('doc_no', e.target.value)}
                                error={approveForm.errors.doc_no}
                                required
                            />
                            <TextField
                                label="Page No."
                                value={approveForm.data.page_no}
                                onChange={(e) => approveForm.setData('page_no', e.target.value)}
                                error={approveForm.errors.page_no}
                                required
                            />
                            <TextField
                                label="Book No."
                                value={bookNoDraft}
                                onChange={handleBookNoChange}
                                placeholder="e.g. V"
                                error={approveForm.errors.book_no}
                                required
                            />
                            <TextField
                                label="Series year"
                                value={approveForm.data.series_year}
                                onChange={(e) => approveForm.setData('series_year', e.target.value)}
                                error={approveForm.errors.series_year}
                                maxLength={4}
                                required
                            />
                            <div className="flex gap-3 sm:col-span-2">
                                <PrimaryButton disabled={approveForm.processing}>
                                    {approveForm.processing ? 'Approving…' : 'Approve request'}
                                </PrimaryButton>
                            </div>
                        </form>
                    </CardBody>
                </Card>
            )}

            <ConfirmModal
                show={deleteOpen}
                onClose={() => setDeleteOpen(false)}
                onConfirm={() => router.delete(route('bond-requests.destroy', bondRequest.id))}
                title="Delete Bond Request"
                message="This action cannot be undone."
                confirmLabel="Delete"
                danger
            />
        </AppLayout>
    );
}

function Detail({ label, value, className = '', capitalize = true }) {
    return (
        <div className={className}>
            <dt className="text-xs font-medium uppercase text-slate-500">{label}</dt>
            <dd className={`mt-1 text-sm text-slate-900 ${capitalize ? 'capitalize' : ''}`}>{value ?? '—'}</dd>
        </div>
    );
}
