import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import Card, { CardBody, CardHeader } from '@/Components/UI/Card';
import ConfirmModal from '@/Components/UI/ConfirmModal';
import FileDownloadLink from '@/Components/UI/FileDownloadLink';
import Modal from '@/Components/Modal';
import { SelectField, TextAreaField, TextField } from '@/Components/UI/FormField';
import StatusBadge from '@/Components/UI/StatusBadge';
import { useToast } from '@/Contexts/ToastContext';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateInWords } from '@/lib/formatDateInWords';
import { formatBookNoDisplay, formatBookNoInput } from '@/lib/romanNumerals';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

export default function Show({
    bondRequest,
    supportingDocuments = [],
    canUpdate,
    canResubmit = false,
    canDelete,
    canApprove,
    canNotarize,
    canGenerateCertificate,
    canReturnFund = false,
    hasCertificate,
    hasDocx,
    certificateVersions = [],
    canMakeVersionCurrent,
    canDeleteCertificateVersion,
    showVersionGeneratedBy = true,
    signatoryOptions,
    notaryOptions,
}) {
    const { addToast } = useToast();
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [versionToDelete, setVersionToDelete] = useState(null);
    const [rejectOpen, setRejectOpen] = useState(false);
    const [returnFundOpen, setReturnFundOpen] = useState(false);

    // ── Reject form ───────────────────────────────────────────────────────────
    const rejectForm = useForm({
        remarks: '',
    });

    // ── Approve form ──────────────────────────────────────────────────────────
    const [bookNoDraft, setBookNoDraft] = useState('');
    const bookNoRef = useRef(null);

    const approveForm = useForm({
        signatory_id: '',
        include_signatory_signature: false,
        notary_id: '',
        doc_no: '',
        page_no: '',
        book_no: '',
        series_year: bondRequest.series_year || String(new Date().getFullYear()),
    });

    // ── Generate Certificate form ─────────────────────────────────────────────
    const [generateBookNoDraft, setGenerateBookNoDraft] = useState(
        formatBookNoDisplay(bondRequest.book_no) || '',
    );
    const generateBookNoRef = useRef(null);
    const returnFundForm = useForm({
        remarks: '',
    });


    // True when any certificate details were saved during approval.
    const detailsAlreadySaved = Boolean(
        bondRequest.signatory_id ||
            bondRequest.notary_id ||
            bondRequest.doc_no ||
            bondRequest.page_no ||
            bondRequest.book_no ||
            bondRequest.series_year,
    );

    // The approver opted to edit the saved details. Defaults to false so that an
    // approved request lands directly on the generate-ready (compact) view.
    const [forceEditGenerateDetails, setForceEditGenerateDetails] = useState(false);
    const showGenerateForm = !detailsAlreadySaved || forceEditGenerateDetails;

    const generateForm = useForm({
        signatory_id: bondRequest.signatory_id ? String(bondRequest.signatory_id) : '',
        include_signatory_signature: Boolean(bondRequest.include_signatory_signature),
        notary_id: bondRequest.notary_id ? String(bondRequest.notary_id) : '',
        doc_no: bondRequest.doc_no || '',
        page_no: bondRequest.page_no || '',
        book_no: bondRequest.book_no || '',
        series_year: bondRequest.series_year || String(new Date().getFullYear()),
    });

    useEffect(() => {
        generateForm.setData({
            signatory_id: bondRequest.signatory_id ? String(bondRequest.signatory_id) : '',
            include_signatory_signature: Boolean(bondRequest.include_signatory_signature),
            notary_id: bondRequest.notary_id ? String(bondRequest.notary_id) : '',
            doc_no: bondRequest.doc_no || '',
            page_no: bondRequest.page_no || '',
            book_no: bondRequest.book_no || '',
            series_year: bondRequest.series_year || String(new Date().getFullYear()),
        });
        setGenerateBookNoDraft(formatBookNoDisplay(bondRequest.book_no) || '');
    }, [
        bondRequest.id,
        bondRequest.signatory_id,
        bondRequest.include_signatory_signature,
        bondRequest.notary_id,
        bondRequest.doc_no,
        bondRequest.page_no,
        bondRequest.book_no,
        bondRequest.series_year,
    ]);

    const displayTin = bondRequest.signatory?.tin || bondRequest.tin || '—';

    // ── Shared option lists ───────────────────────────────────────────────────
    const signatorySelectOptions = useMemo(
        () => [{ value: '', label: 'Select signatory…' }, ...signatoryOptions],
        [signatoryOptions],
    );

    const selectedApproveSignatory = signatoryOptions.find(
        (o) => String(o.value) === String(approveForm.data.signatory_id),
    );

    const selectedGenerateSignatory = signatoryOptions.find(
        (o) => String(o.value) === String(generateForm.data.signatory_id),
    );

    const selectedGenerateNotary = notaryOptions.find(
        (o) => String(o.value) === String(generateForm.data.notary_id),
    );

    const handleApproveSignatoryChange = (event) => {
        const signatoryId = event.target.value;
        approveForm.setData((current) => ({
            ...current,
            signatory_id: signatoryId,
            include_signatory_signature: signatoryId ? current.include_signatory_signature : false,
        }));
    };

    const submitReturnFund = (e) => {
        e.preventDefault();

        returnFundForm.post(route('bond-requests.return-fund', bondRequest.id), {
            preserveScroll: true,
            onSuccess: () => {
                setReturnFundOpen(false);
                returnFundForm.reset();
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];

                if (firstError) {
                    addToast(Array.isArray(firstError) ? firstError[0] : firstError, 'error');
                }
            },
        });
    };
    const handleGenerateSignatoryChange = (event) => {
        const signatoryId = event.target.value;
        generateForm.setData((current) => ({
            ...current,
            signatory_id: signatoryId,
            include_signatory_signature: signatoryId ? current.include_signatory_signature : false,
        }));
    };

    const notarySelectOptions = useMemo(
        () => [{ value: '', label: 'Select notary…' }, ...notaryOptions],
        [notaryOptions],
    );

    useEffect(
        () => () => {
            clearTimeout(bookNoRef.current);
            clearTimeout(generateBookNoRef.current);
        },
        [],
    );

    const status = bondRequest.status?.value || bondRequest.status;

    // ── Approve handlers ──────────────────────────────────────────────────────
    const handleApproveBookNoChange = (event) => {
        const value = event.target.value;
        setBookNoDraft(value);
        clearTimeout(bookNoRef.current);
        bookNoRef.current = setTimeout(() => {
            const formatted = formatBookNoInput(value);
            setBookNoDraft(formatted);
            approveForm.setData('book_no', formatted);
        }, 750);
    };

    const submitApprove = (e) => {
        e.preventDefault();
        clearTimeout(bookNoRef.current);
        const formatted = formatBookNoInput(bookNoDraft);
        setBookNoDraft(formatted);
        approveForm.transform((current) => ({ ...current, book_no: formatted }));
        approveForm.post(route('bond-requests.approve', bondRequest.id), {
            preserveScroll: true,
        });
    };

    // ── Generate handlers ─────────────────────────────────────────────────────
    const handleGenerateBookNoChange = (event) => {
        const value = event.target.value;
        setGenerateBookNoDraft(value);
        clearTimeout(generateBookNoRef.current);
        generateBookNoRef.current = setTimeout(() => {
            const formatted = formatBookNoInput(value);
            setGenerateBookNoDraft(formatted);
            generateForm.setData('book_no', formatted);
        }, 750);
    };

    const submitGenerate = (e) => {
        e.preventDefault();
        clearTimeout(generateBookNoRef.current);
        const formatted = formatBookNoInput(generateBookNoDraft);
        setGenerateBookNoDraft(formatted);
        generateForm.transform((current) => ({ ...current, book_no: formatted }));
        generateForm.post(route('bond-requests.generate-certificate', bondRequest.id), {
            preserveScroll: true,
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) {
                    addToast(Array.isArray(firstError) ? firstError[0] : firstError, 'error');
                } else {
                    addToast('Unable to generate confirmation. Please check the form details.', 'error');
                }
            },
        });
    };

    const firstGenerateError = Object.values(generateForm.errors)[0];

    // ── Derived display values ────────────────────────────────────────────────
    const isCarEndorsementRequest = (bondRequest.certificate_type?.value || bondRequest.certificate_type) === 'car_certificate'
        && Boolean(bondRequest.include_endorsement_number);
    const addresses = [bondRequest.address_1, bondRequest.address_2, bondRequest.address_3].filter(Boolean);
    const inceptionDate = bondRequest.inception_date ? String(bondRequest.inception_date).substring(0, 10) : null;
    const extensionPeriodStart = bondRequest.extension_period_start
        ? String(bondRequest.extension_period_start).substring(0, 10)
        : null;
    const hasCertificateDetails = bondRequest.signatory_id || bondRequest.notary_id || bondRequest.doc_no;

    return (
        <AppLayout
            title={`Bond ${bondRequest.bond_number}`}
            actions={
                <div className="flex flex-wrap gap-2">
                    {canResubmit && (
                        <PrimaryButton
                            type="button"
                            onClick={() => router.post(route('bond-requests.resubmit', bondRequest.id))}
                        >
                            Resubmit request
                        </PrimaryButton>
                    )}
                    {canUpdate && (
                        <Link href={route('bond-requests.edit', bondRequest.id)}>
                            <SecondaryButton>Edit</SecondaryButton>
                        </Link>
                    )}
                    {canApprove && (
                        <SecondaryButton
                            type="button"
                            onClick={() => setRejectOpen(true)}
                        >
                            Request Changes
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
                    {hasCertificate && (
                        <a
                            href={route('bond-requests.view-certificate', bondRequest.id)}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <SecondaryButton type="button">View Confirmation</SecondaryButton>
                        </a>
                    )}
                    {canReturnFund && (
                        <SecondaryButton
                            type="button"
                            onClick={() => setReturnFundOpen(true)}
                            className="!text-red-600"
                        >
                            Return Fund
                        </SecondaryButton>
                    )}
                    {hasCertificate && (
                        <FileDownloadLink href={route('bond-requests.download-certificate', bondRequest.id)}>
                            Download Confirmation
                        </FileDownloadLink>
                    )}
                    {hasDocx && (
                        <FileDownloadLink href={route('bond-requests.download-docx', bondRequest.id)}>
                            Download DOCX
                        </FileDownloadLink>
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
                            </>
                        ) : (
                            <>
                                <Detail label="Bond Number" value={bondRequest.bondTypeMaster?.code ?? bondRequest.bond_number} />
                                <Detail label="Bond" value={bondRequest.bond_label || '—'} className="sm:col-span-2" capitalize={false} />
                                <Detail label="Bond Type" value={bondRequest.bond_type_label} />
                            </>
                        )}
                        <Detail label="TIN" value={displayTin} capitalize={false} />
                        {bondRequest.endorsement_number && (
                            <Detail label="Endorsement No." value={bondRequest.endorsement_number} capitalize={false} />
                        )}
                        <Detail
                            label="Party Type"
                            value={bondRequest.party_type?.label || bondRequest.party_type || '—'}
                            capitalize={false}
                        />
                        <Detail label="Principal" value={bondRequest.principal?.company_name || bondRequest.principal_name} />
                        <Detail label="Obligee" value={bondRequest.obligee?.company_name} />
                        <Detail
                            label="Address"
                            value={addresses.length ? addresses.join(', ') : '—'}
                            className="sm:col-span-2"
                            capitalize={false}
                        />
                        <Detail label="Request Date" value={bondRequest.request_date} />
                        {(!isCarEndorsementRequest || bondRequest.date_issued) && (
                            <Detail label="Date issued" value={bondRequest.date_issued || '—'} />
                        )}
                        {isCarEndorsementRequest && (
                            <>
                                <Detail
                                    label="Extension Period Start"
                                    value={extensionPeriodStart || '—'}
                                />
                                <Detail
                                    label="Validity Extension"
                                    value={bondRequest.validity_extension || '—'}
                                    capitalize={false}
                                />
                            </>
                        )}
                        <Detail label="Inception date" value={inceptionDate || '—'} />
                        {(!isCarEndorsementRequest || inceptionDate) && (
                            <Detail
                                label="Inception date in words"
                                value={inceptionDate ? formatDateInWords(inceptionDate) : '—'}
                                className="sm:col-span-2"
                                capitalize={false}
                            />
                        )}
                        <Detail label="Attention" value={bondRequest.attention || '—'} capitalize={false} />
                        <Detail label="Confirmation type" value={bondRequest.certificate_type_label || '—'} />
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
                        {supportingDocuments.length > 0 && (
                            <Detail
                                label="Supporting documents"
                                value={
                                    <ul className="space-y-1">
                                        {supportingDocuments.map((document) => (
                                            <li key={document.path}>
                                                <a
                                                    href={document.url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-sterling-green hover:underline"
                                                >
                                                    {document.name}
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                }
                                className="sm:col-span-2"
                                capitalize={false}
                            />
                        )}
                        <Detail 
                            label="Notary Required" 
                            value={bondRequest.require_notary ? 'Yes' : 'No'} 
                        />
                        {hasCertificateDetails && (
                            <>
                                <Detail label="Signatory" value={bondRequest.signatory?.name || '—'} />
                                <Detail label="Position" value={bondRequest.signatory_position || bondRequest.signatory?.position || '—'} />
                                <Detail
                                    label="Include signature"
                                    value={bondRequest.include_signatory_signature ? 'Yes' : 'No'}
                                />
                                <Detail label="Notary" value={bondRequest.notary?.name || '—'} />
                                <Detail label="Doc No." value={bondRequest.doc_no || '—'} />
                                <Detail label="Page No." value={bondRequest.page_no || '—'} />
                                <Detail label="Book No." value={formatBookNoDisplay(bondRequest.book_no) || '—'} />
                                <Detail label="Series year" value={bondRequest.series_year || '—'} />
                            </>
                        )}
                        <Detail label="Created By" value={bondRequest.creator?.name} />
                        {bondRequest.remarks && (
                            <Detail 
                                label="Remarks / Reason for Changes" 
                                value={bondRequest.remarks} 
                                className="sm:col-span-2 p-3 bg-orange-50 rounded border border-orange-200"
                                capitalize={false}
                            />
                        )}
                    </dl>
                </CardBody>
            </Card>

            {canApprove && (
                <Card className="mt-6">
                    <CardHeader title="Approver / Super Admin review" />
                    <CardBody>
                        {bondRequest.require_notary && (
                            <div className="mb-5 rounded-xl border border-blue-400 bg-gradient-to-r from-blue-50 via-white to-blue-100 p-4 shadow-sm ring-1 ring-blue-200">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="inline-flex items-center rounded-full bg-blue-600 px-3 py-1 text-xs font-bold uppercase tracking-wide text-white">
                                        Notary Required
                                    </span>
                                    <span className="text-sm font-semibold text-blue-900">
                                        Do not miss this before approval
                                    </span>
                                </div>
                                <p className="mt-3 text-sm font-medium text-blue-900">
                                    This confirmation must include a notary. The approver or super admin should select a notary in the review form before generating or finalizing the confirmation.
                                </p>
                            </div>
                        )}
                        <p className="mb-4 text-sm text-slate-600">
                            Confirmation details are optional at approval. Complete them now or when generating the confirmation.
                        </p>
                        <form onSubmit={submitApprove} className="grid gap-4 sm:grid-cols-2">
                            <SelectField
                                label="Signatory (optional)"
                                value={approveForm.data.signatory_id}
                                onChange={handleApproveSignatoryChange}
                                options={signatorySelectOptions}
                                error={approveForm.errors.signatory_id}
                            />
                            <TextField
                                label="Position"
                                value={selectedApproveSignatory?.position || '—'}
                                readOnly
                                className="bg-slate-50"
                            />
                            {approveForm.data.signatory_id && (
                                <div className="sm:col-span-2 space-y-3">
                                    <label className="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            checked={Boolean(approveForm.data.include_signatory_signature)}
                                            onChange={(e) =>
                                                approveForm.setData('include_signatory_signature', e.target.checked)
                                            }
                                            className="rounded border-slate-300 text-sterling-green focus:ring-sterling-gold"
                                        />
                                        <span className="text-sm font-medium text-slate-800">Include signature</span>
                                    </label>
                                    {selectedApproveSignatory?.signature_url && (
                                        <img
                                            src={selectedApproveSignatory.signature_url}
                                            alt="Signatory signature preview"
                                            className="h-16 w-auto rounded border border-slate-200 bg-white object-contain p-1"
                                        />
                                    )}
                                    <InputError message={approveForm.errors.include_signatory_signature} />
                                </div>
                            )}
                            <SelectField
                                label="Notary (optional)"
                                value={approveForm.data.notary_id}
                                onChange={(e) => approveForm.setData('notary_id', e.target.value)}
                                options={notarySelectOptions}
                                error={approveForm.errors.notary_id}
                            />
                            <TextField
                                label="Doc No. (optional)"
                                value={approveForm.data.doc_no}
                                onChange={(e) => approveForm.setData('doc_no', e.target.value)}
                                error={approveForm.errors.doc_no}
                            />
                            <TextField
                                label="Page No. (optional)"
                                value={approveForm.data.page_no}
                                onChange={(e) => approveForm.setData('page_no', e.target.value)}
                                error={approveForm.errors.page_no}
                            />
                            <TextField
                                label="Book No. (optional)"
                                value={bookNoDraft}
                                onChange={handleApproveBookNoChange}
                                placeholder="e.g. V"
                                error={approveForm.errors.book_no}
                            />
                            <TextField
                                label="Series year (optional)"
                                value={approveForm.data.series_year}
                                onChange={(e) => approveForm.setData('series_year', e.target.value)}
                                error={approveForm.errors.series_year}
                                maxLength={4}
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

            {['approved', 'notarized'].includes(status) && !canGenerateCertificate && (
                <Card className="mt-6">
                    <CardHeader title="Confirmation" />
                    <CardBody>
                        {hasCertificate ? (
                            <div className="flex flex-wrap items-center gap-3">
                                <p className="text-sm text-slate-600">Your confirmation is ready.</p>
                                <a
                                    href={route('bond-requests.view-certificate', bondRequest.id)}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <SecondaryButton type="button">View Confirmation</SecondaryButton>
                                </a>
                                <FileDownloadLink href={route('bond-requests.download-certificate', bondRequest.id)}>
                                    Download Confirmation
                                </FileDownloadLink>
                            </div>
                        ) : (
                            <p className="text-sm text-slate-500">Confirmation not yet available.</p>
                        )}
                    </CardBody>
                </Card>
            )}

            {canGenerateCertificate && (
                <Card className="mt-6">
                    <CardHeader
                        title="Generate Confirmation"
                        action={
                            detailsAlreadySaved && (
                                <button
                                    type="button"
                                    onClick={() => setForceEditGenerateDetails((v) => !v)}
                                    className="text-sm text-sterling-green hover:underline"
                                >
                                    {forceEditGenerateDetails ? 'Hide details' : 'Edit details'}
                                </button>
                            )
                        }
                    />
                    <CardBody>
                        {!showGenerateForm ? (
                            /* ── Compact view: details saved during approval ── */
                            <div className="space-y-4">
                                <dl className="grid gap-3 sm:grid-cols-3 text-sm">
                                    <div>
                                        <dt className="text-xs font-medium uppercase text-slate-500">Signatory</dt>
                                        <dd className="mt-1 text-slate-900">
                                            {bondRequest.signatory?.name || selectedGenerateSignatory?.label || '—'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs font-medium uppercase text-slate-500">Position</dt>
                                        <dd className="mt-1 text-slate-900">
                                            {bondRequest.signatory_position || bondRequest.signatory?.position || selectedGenerateSignatory?.position || '—'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs font-medium uppercase text-slate-500">Include signature</dt>
                                        <dd className="mt-1 text-slate-900">
                                            {bondRequest.include_signatory_signature ? 'Yes' : 'No'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs font-medium uppercase text-slate-500">Notary</dt>
                                        <dd className="mt-1 text-slate-900">
                                            {bondRequest.notary?.name || selectedGenerateNotary?.label || '—'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs font-medium uppercase text-slate-500">Doc No.</dt>
                                        <dd className="mt-1 text-slate-900">{bondRequest.doc_no || '—'}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs font-medium uppercase text-slate-500">Page No.</dt>
                                        <dd className="mt-1 text-slate-900">{bondRequest.page_no || '—'}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs font-medium uppercase text-slate-500">Book No.</dt>
                                        <dd className="mt-1 text-slate-900">
                                            {formatBookNoDisplay(bondRequest.book_no) || '—'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs font-medium uppercase text-slate-500">Series year</dt>
                                        <dd className="mt-1 text-slate-900">{bondRequest.series_year || '—'}</dd>
                                    </div>
                                </dl>
                                {firstGenerateError && (
                                    <p className="text-sm text-red-600">
                                        {Array.isArray(firstGenerateError) ? firstGenerateError[0] : firstGenerateError}
                                    </p>
                                )}
                                <form onSubmit={submitGenerate}>
                                    <PrimaryButton disabled={generateForm.processing}>
                                        {generateForm.processing ? 'Generating…' : hasCertificate ? 'Regenerate Confirmation' : 'Generate Confirmation'}
                                    </PrimaryButton>
                                </form>
                            </div>
                        ) : (
                            /* ── Full editable form ── */
                            <form onSubmit={submitGenerate} className="grid gap-4 sm:grid-cols-2">
                                <SelectField
                                    label="Signatory (optional)"
                                    value={generateForm.data.signatory_id}
                                    onChange={handleGenerateSignatoryChange}
                                    options={signatorySelectOptions}
                                    error={generateForm.errors.signatory_id}
                                />
                                <TextField
                                    label="Position"
                                    value={selectedGenerateSignatory?.position || '—'}
                                    readOnly
                                    className="bg-slate-50"
                                />
                                {generateForm.data.signatory_id && (
                                    <div className="sm:col-span-2 space-y-3">
                                        <label className="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                checked={Boolean(generateForm.data.include_signatory_signature)}
                                                onChange={(e) =>
                                                    generateForm.setData('include_signatory_signature', e.target.checked)
                                                }
                                                className="rounded border-slate-300 text-sterling-green focus:ring-sterling-gold"
                                            />
                                            <span className="text-sm font-medium text-slate-800">Include signature</span>
                                        </label>
                                        {selectedGenerateSignatory?.signature_url && (
                                            <img
                                                src={selectedGenerateSignatory.signature_url}
                                                alt="Signatory signature preview"
                                                className="h-16 w-auto rounded border border-slate-200 bg-white object-contain p-1"
                                            />
                                        )}
                                        <InputError message={generateForm.errors.include_signatory_signature} />
                                    </div>
                                )}
                                <SelectField
                                    label="Notary (optional)"
                                    value={generateForm.data.notary_id}
                                    onChange={(e) => generateForm.setData('notary_id', e.target.value)}
                                    options={notarySelectOptions}
                                    error={generateForm.errors.notary_id}
                                />
                                <TextField
                                    label="Doc No. (optional)"
                                    value={generateForm.data.doc_no}
                                    onChange={(e) => generateForm.setData('doc_no', e.target.value)}
                                    error={generateForm.errors.doc_no}
                                />
                                <TextField
                                    label="Page No. (optional)"
                                    value={generateForm.data.page_no}
                                    onChange={(e) => generateForm.setData('page_no', e.target.value)}
                                    error={generateForm.errors.page_no}
                                />
                                <TextField
                                    label="Book No. (optional)"
                                    value={generateBookNoDraft}
                                    onChange={handleGenerateBookNoChange}
                                    placeholder="e.g. V"
                                    error={generateForm.errors.book_no}
                                />
                                <TextField
                                    label="Series year (optional)"
                                    value={generateForm.data.series_year}
                                    onChange={(e) => generateForm.setData('series_year', e.target.value)}
                                    error={generateForm.errors.series_year}
                                    maxLength={4}
                                />
                                <div className="flex flex-col gap-2 sm:col-span-2">
                                    <PrimaryButton disabled={generateForm.processing}>
                                        {generateForm.processing ? 'Generating…' : hasCertificate ? 'Regenerate Confirmation' : 'Generate Confirmation'}
                                    </PrimaryButton>
                                </div>
                            </form>
                        )}
                    </CardBody>
                </Card>
            )}

            {certificateVersions.length > 0 && (
                <Card className="mt-6">
                    <CardHeader title="Confirmation Versions" />
                    <CardBody>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead>
                                    <tr className="text-left text-xs font-medium uppercase text-slate-500">
                                        <th className="px-3 py-2">Version</th>
                                        <th className="px-3 py-2">Type</th>
                                        {showVersionGeneratedBy && (
                                            <th className="px-3 py-2">Generated By</th>
                                        )}
                                        <th className="px-3 py-2">Generated</th>
                                        <th className="px-3 py-2">Status</th>
                                        <th className="px-3 py-2">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {certificateVersions.map((version) => (
                                        <tr key={version.id}>
                                            <td className="px-3 py-3 font-medium text-slate-900">v{version.version_number}</td>
                                            <td className="px-3 py-3 text-slate-700">{version.certificate_type_label || '—'}</td>
                                            {showVersionGeneratedBy && (
                                                <td className="px-3 py-3 text-slate-700">{version.generated_by?.name || '—'}</td>
                                            )}
                                            <td className="px-3 py-3 text-slate-700">
                                                {version.generated_at
                                                    ? new Date(version.generated_at).toLocaleString()
                                                    : '—'}
                                            </td>
                                            <td className="px-3 py-3">
                                                {version.is_current ? (
                                                    <span className="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">
                                                        Current
                                                    </span>
                                                ) : (
                                                    <span className="text-slate-500">Previous</span>
                                                )}
                                            </td>
                                            <td className="px-3 py-3">
                                                <div className="flex flex-wrap gap-2">
                                                    {(version.has_pdf || version.has_docx) && (
                                                        <a
                                                            href={route('certificate-versions.view', version.id)}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                        >
                                                            <SecondaryButton type="button">View PDF</SecondaryButton>
                                                        </a>
                                                    )}
                                                    {(version.has_pdf || version.has_docx) && (
                                                        <FileDownloadLink href={route('certificate-versions.download', version.id)}>
                                                            Download PDF
                                                        </FileDownloadLink>
                                                    )}
                                                    {version.has_docx && (
                                                        <FileDownloadLink href={route('certificate-versions.download-docx', version.id)}>
                                                            Download DOCX
                                                        </FileDownloadLink>
                                                    )}
                                                    {canMakeVersionCurrent && !version.is_current && (
                                                        <SecondaryButton
                                                            type="button"
                                                            onClick={() =>
                                                                router.patch(route('certificate-versions.make-current', version.id), {}, {
                                                                    preserveScroll: true,
                                                                })
                                                            }
                                                        >
                                                            Make Current
                                                        </SecondaryButton>
                                                    )}
                                                    {canDeleteCertificateVersion && !version.is_current && (
                                                        <SecondaryButton
                                                            type="button"
                                                            onClick={() => setVersionToDelete(version)}
                                                            className="!text-red-600"
                                                        >
                                                            Delete
                                                        </SecondaryButton>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
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

            <ConfirmModal
                show={versionToDelete !== null}
                onClose={() => setVersionToDelete(null)}
                onConfirm={() =>
                    router.delete(route('certificate-versions.destroy', versionToDelete.id), {
                        preserveScroll: true,
                        onSuccess: () => setVersionToDelete(null),
                    })
                }
                title={
                    versionToDelete
                        ? `Delete confirmation version v${versionToDelete.version_number}?`
                        : 'Delete confirmation version?'
                }
                message="This permanently removes the version and its files. This cannot be undone."
                confirmLabel="Delete"
                danger
            />

            {/* Reject/Request Changes Modal */}
            {rejectOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-md rounded-lg bg-white p-6 shadow-lg">
                        <h3 className="text-lg font-semibold text-slate-900 mb-4">Request Changes</h3>
                        <form onSubmit={(e) => {
                            e.preventDefault();
                            rejectForm.post(route('bond-requests.reject', bondRequest.id), {
                                preserveScroll: true,
                                onSuccess: () => {
                                    setRejectOpen(false);
                                    rejectForm.reset();
                                },
                            });
                        }}>
                            <div className="mb-4">
                                <label className="block text-sm font-medium text-slate-700 mb-2">
                                    Remarks / Reason for Changes <span className="text-red-600">*</span>
                                </label>
                                <textarea
                                    value={rejectForm.data.remarks}
                                    onChange={(e) => rejectForm.setData('remarks', e.target.value)}
                                    placeholder="Please explain what changes are needed..."
                                    className="w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 placeholder:text-slate-400 focus:border-sterling-green focus:outline-none focus:ring-1 focus:ring-sterling-green"
                                    rows="4"
                                />
                                {rejectForm.errors.remarks && (
                                    <p className="mt-1 text-sm text-red-600">{rejectForm.errors.remarks}</p>
                                )}
                            </div>
                            <div className="flex justify-end gap-3">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setRejectOpen(false);
                                        rejectForm.reset();
                                    }}
                                    className="px-4 py-2 text-sm font-medium text-slate-700 border border-slate-300 rounded-md hover:bg-slate-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={rejectForm.processing || !rejectForm.data.remarks.trim()}
                                    className="px-4 py-2 text-sm font-medium text-white bg-orange-600 rounded-md hover:bg-orange-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {rejectForm.processing ? 'Processing...' : 'Request Changes'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
            <Modal show={returnFundOpen} onClose={() => setReturnFundOpen(false)} maxWidth="md">
                <form onSubmit={submitReturnFund} className="p-6">
                    <h3 className="text-lg font-semibold text-sterling-green">Return Fund</h3>
                    <p className="mt-2 text-sm text-slate-600">
                        This will return the full notary fee to the requester&apos;s branch and mark the request as returned.
                    </p>
                    <div className="mt-4 space-y-2">
                        <TextAreaField
                            label="Remarks / Feedback"
                            value={returnFundForm.data.remarks}
                            onChange={(e) => returnFundForm.setData('remarks', e.target.value)}
                            rows={4}
                            className="min-h-[120px] resize-y"
                            error={returnFundForm.errors.remarks}
                            placeholder="Optional reason or notes for the fund return"
                        />
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton
                            type="button"
                            onClick={() => {
                                setReturnFundOpen(false);
                                returnFundForm.reset();
                            }}
                        >
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton type="submit" disabled={returnFundForm.processing}>
                            {returnFundForm.processing ? 'Processing…' : 'Return Fund'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
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
