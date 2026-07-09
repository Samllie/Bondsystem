import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import Card, { CardBody } from '@/Components/UI/Card';
import EditableCombobox from '@/Components/UI/EditableCombobox';
import { TextAreaField, TextField } from '@/Components/UI/FormField';
import InputError from '@/Components/InputError';
import BackLink from '@/Components/UI/BackLink';
import AppLayout from '@/Layouts/AppLayout';
import { amountInWords } from '@/lib/amountInWords';
import { buildBondValue, buildCarValue } from '@/lib/bondFormat';
import { formatDateInWords } from '@/lib/formatDateInWords';
import { formatAmountDisplay, parseAmountInput } from '@/lib/formatAmount';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

function formatDate(value) {
    if (!value) {
        return '';
    }

    return String(value).substring(0, 10);
}

function todayIso() {
    return new Date().toISOString().substring(0, 10);
}

function formatExpiryForForm(value) {
    if (!value) {
        return '';
    }

    const text = String(value).trim();

    if (/^\d{4}-\d{2}-\d{2}/.test(text)) {
        return text.substring(0, 10);
    }

    return text;
}

function splitAddressLines(value) {
    if (!value) {
        return [];
    }

    return String(value)
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line !== '')
        .slice(0, 3);
}

function emptyAddressLine() {
    return { address: '', ctm: '', province: '' };
}

function formatValidityExtensionForForm(value) {
    if (!value) {
        return '';
    }

    return String(value).trim().replace(/^\(+|\)+$/g, '').trim();
}

export default function Form({
    bondRequest,
    selectedPrincipal,
    selectedObligee,
    bondTypeOptions,
    certificateTypeOptions,
    partyTypeOptions,
    supportingDocuments = [],
    requesterBranchCode = '',
    branchFund = null,
}) {
    const isEdit = Boolean(bondRequest?.id);
    const authUser = usePage().props?.auth?.user;
    const isRequesterRole = authUser?.role?.slug === 'requester';
    const php = (v) => Number(v).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });
    const [removedSupportingDocuments, setRemovedSupportingDocuments] = useState([]);

    const remainingSupportingDocumentSlots = Math.max(
        0,
        5 - (supportingDocuments?.length ?? 0) + removedSupportingDocuments.length,
    );

    const { data, setData, post, transform, processing, errors } = useForm({
        obligee_id: bondRequest?.obligee_id || '',
        obligee_name: bondRequest?.obligee_name || selectedObligee?.company_name || '',
        address_1: bondRequest?.address_1 || '',
        address_2: bondRequest?.address_2 || '',
        address_3: bondRequest?.address_3 || '',
        bond_type_id: bondRequest?.bond_type_id || '',
        bond_type_label: bondRequest?.bondTypeMaster?.name || '',
        principal_id: bondRequest?.principal_id || '',
        principal_name: bondRequest?.principal_name || selectedPrincipal?.company_name || '',
        request_date: formatDate(bondRequest?.request_date) || todayIso(),
        amount: bondRequest?.amount || '',
        project_name: bondRequest?.project_name || '',
        date_issued: formatDate(bondRequest?.date_issued) || todayIso(),
        inception_date: formatDate(bondRequest?.inception_date),
        attention: bondRequest?.attention || '',
        supporting_documents: [],
        removed_supporting_documents: [],
        certificate_type: bondRequest?.certificate_type?.value || bondRequest?.certificate_type || 'bond_certificate',
        party_type:
            (bondRequest?.certificate_type?.value || bondRequest?.certificate_type || 'bond_certificate') === 'car_certificate'
                ? 'government'
                : bondRequest?.party_type?.value || bondRequest?.party_type || 'private',
        include_endorsement_number: Boolean(
            bondRequest?.include_endorsement_number ?? bondRequest?.endorsement_number,
        ),
        endorsement_number: bondRequest?.endorsement_number || '',
        extension_period_start: formatDate(bondRequest?.extension_period_start) || '',
        validity_extension: formatValidityExtensionForForm(bondRequest?.validity_extension),
        branch_code: requesterBranchCode || '',
        bond_number: bondRequest?.bond_number || '',
        car: bondRequest?.car || buildCarValue(requesterBranchCode),
        authorized_representative: bondRequest?.authorized_representative || '',
        expiry_date: formatExpiryForForm(bondRequest?.expiry_date),
        require_notary: Boolean(bondRequest?.require_notary ?? false),
    });

    const hasInsufficientBranchFund = branchFund && data.require_notary && !branchFund.canSubmit;

    const [addressLines, setAddressLines] = useState(() => {
        const addressEntries = splitAddressLines(bondRequest?.address_1);
        const ctmEntries = splitAddressLines(bondRequest?.address_2);
        const provinceEntries = splitAddressLines(bondRequest?.address_3);
        const maxRows = Math.max(addressEntries.length, ctmEntries.length, provinceEntries.length, 1);

        return Array.from({ length: Math.min(maxRows, 3) }, (_, index) => ({
            address: addressEntries[index] || '',
            ctm: ctmEntries[index] || '',
            province: provinceEntries[index] || '',
        }));
    });

    const amountWords = useMemo(() => amountInWords(data.amount), [data.amount]);
    const requestDateInWords = useMemo(() => formatDateInWords(data.request_date), [data.request_date]);
    const dateIssuedInWords = useMemo(() => formatDateInWords(data.date_issued), [data.date_issued]);
    const inceptionDateInWords = useMemo(() => formatDateInWords(data.inception_date), [data.inception_date]);

    useEffect(() => {
        const joinedAddress = addressLines
            .map((line) => line.address.trim())
            .filter((line) => line !== '')
            .join('\n');
        const joinedCtm = addressLines
            .map((line) => line.ctm.trim())
            .filter((line) => line !== '')
            .join('\n');
        const joinedProvince = addressLines
            .map((line) => line.province.trim())
            .filter((line) => line !== '')
            .join('\n');

        setData((current) => ({
            ...current,
            address_1: joinedAddress,
            address_2: joinedCtm,
            address_3: joinedProvince,
        }));
    }, [addressLines, setData]);

    const handleBondTypeChange = (event) => {
        const nextBondTypeLabel = event.target.value;
        const selectedBondType = bondTypeOptions.find(
            (option) => option.label.toLowerCase() === nextBondTypeLabel.toLowerCase(),
        );
        const nextBondTypeNumber = selectedBondType?.code || '';

        setData((current) => ({
            ...current,
            bond_type_id: selectedBondType?.value || '',
            bond_type_label: nextBondTypeLabel,
            bond_number: buildBondValue(nextBondTypeLabel, current.branch_code, nextBondTypeNumber),
        }));
    };

    const handleObligeeSelect = (option) => {
        setData((current) => ({
            ...current,
            obligee_id: option.id,
            obligee_name: option.label || option.company_name || '',
        }));

        setAddressLines((current) => {
            const next = [...current];
            if (next.length === 0) {
                next.push(emptyAddressLine());
            }

            next[0] = {
                address: option?.business_address ?? '',
                ctm: option?.business_ctm ?? '',
                province: option?.business_province ?? '',
            };

            return next.slice(0, 3);
        });
    };

    const updateAddressLine = (index, key, value) => {
        setAddressLines((current) =>
            current.map((line, lineIndex) => (lineIndex === index ? { ...line, [key]: value } : line)),
        );
    };

    const addAddressLine = () => {
        setAddressLines((current) => {
            if (current.length >= 3) {
                return current;
            }

            return [...current, emptyAddressLine()];
        });
    };

    const removeAddressLine = (indexToRemove) => {
        setAddressLines((current) => {
            if (current.length <= 1 || indexToRemove === 0) {
                return current;
            }

            return current.filter((_, index) => index !== indexToRemove);
        });
    };

    const submit = (e) => {
        e.preventDefault();

        const options = { forceFormData: true };
        const appendRemovedDocuments = (current) => ({
            ...current,
            removed_supporting_documents: removedSupportingDocuments,
            require_notary: current.require_notary ? 1 : 0,
            include_endorsement_number: current.include_endorsement_number ? 1 : 0,
        });

        if (isEdit) {
            transform((current) => appendRemovedDocuments(current));
            post(route('bond-requests.update.post', bondRequest.id), options);
        } else {
            transform((current) => appendRemovedDocuments(current));
            post(route('bond-requests.store'), options);
        }
    };

    const handleSupportingDocumentsChange = (event) => {
        const files = Array.from(event.target.files ?? []).slice(0, remainingSupportingDocumentSlots);
        setData('supporting_documents', files);
        event.target.value = '';
    };

    const toggleRemoveSupportingDocument = (path) => {
        setRemovedSupportingDocuments((current) =>
            current.includes(path) ? current.filter((item) => item !== path) : [...current, path],
        );
    };

    const visibleSupportingDocuments = (supportingDocuments ?? []).filter(
        (document) => !removedSupportingDocuments.includes(document.path),
    );

    const principalInitial = selectedPrincipal
        ? { id: selectedPrincipal.id, company_name: selectedPrincipal.company_name, label: selectedPrincipal.company_name }
        : null;

    const obligeeInitial = selectedObligee
        ? {
              id: selectedObligee.id,
              company_name: selectedObligee.company_name,
              label: selectedObligee.label || selectedObligee.company_name,
          }
        : null;

    const selectedBondTypeLabel = useMemo(() => {
        if (data.bond_type_label) {
            return data.bond_type_label;
        }

        const selectedBondType = bondTypeOptions.find(
            (option) => String(option.value) === String(data.bond_type_id),
        );

        return selectedBondType?.label || '';
    }, [bondTypeOptions, data.bond_type_id, data.bond_type_label]);

    const selectedBondType = useMemo(
        () => bondTypeOptions.find((option) => String(option.value) === String(data.bond_type_id)),
        [bondTypeOptions, data.bond_type_id],
    );

    const bondTypeBondNumber = useMemo(() => {
        if (selectedBondType?.code) {
            return selectedBondType.code;
        }

        if (bondRequest?.bondTypeMaster?.code) {
            return bondRequest.bondTypeMaster.code;
        }

        return '';
    }, [bondRequest, selectedBondType]);

    const isCarCertificate = data.certificate_type === 'car_certificate';

    const handleCertificateTypeChange = (value) => {
        setData((current) => ({
            ...current,
            certificate_type: value,
            car:
                value === 'car_certificate' && !current.car
                    ? buildCarValue(requesterBranchCode)
                    : current.car,
            authorized_representative: value === 'car_certificate' ? current.authorized_representative : '',
            party_type: value === 'car_certificate' ? 'government' : current.party_type,
            include_endorsement_number: value === 'car_certificate' ? current.include_endorsement_number : current.include_endorsement_number,
            endorsement_number: current.endorsement_number,
        }));
    };

    const handleEndorsementToggle = (checked) => {
        setData((current) => ({
            ...current,
            include_endorsement_number: checked,
            endorsement_number: checked ? current.endorsement_number : '',
            extension_period_start: checked ? current.extension_period_start : '',
            validity_extension: checked ? current.validity_extension : '',
        }));
    };

    const formErrorMessages = Object.entries(errors).filter(
        ([key, message]) => message && !['obligee_id', 'obligee_name', 'principal_id', 'principal_name'].includes(key),
    );

    return (
        <AppLayout title={isEdit ? 'Edit Bond Request' : 'New Bond Request'}>
            <Head title={isEdit ? 'Edit Bond Request' : 'New Bond Request'} />

            <BackLink
                href={isEdit ? route('bond-requests.show', bondRequest.id) : route('bond-requests.index')}
            >
                {isEdit ? 'Back to Bond Request' : 'Back to Bond Requests'}
            </BackLink>

            <Card className="max-w-3xl">
                <CardBody>
                    {hasInsufficientBranchFund && (
                        <div className="mb-6 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            <p className="font-semibold">Insufficient branch fund</p>
                            <p className="mt-1">
                                {branchFund.branchName ? `${branchFund.branchName} fund` : 'Your branch fund'} is{' '}
                                {php(branchFund.balance)}. A minimum balance of {php(branchFund.minimumBalance)} is required
                                when notary is requested.
                                {!isEdit && ' Please submit a deposit before creating a new request.'}
                            </p>
                        </div>
                    )}

                    {formErrorMessages.length > 0 && (
                        <div className="mb-6 rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
                            <p className="font-semibold">Please fix the following before saving:</p>
                            <ul className="mt-2 list-disc space-y-1 pl-5">
                                {formErrorMessages.map(([key, message]) => (
                                    <li key={key}>{Array.isArray(message) ? message[0] : message}</li>
                                ))}
                            </ul>
                        </div>
                    )}

                    <form onSubmit={submit} encType="multipart/form-data" className="space-y-6">
                        <section className="space-y-4">
                            <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Confirmation request</h2>
                            <div className="grid gap-3 sm:grid-cols-2">
                                {certificateTypeOptions.map((option) => (
                                    <label
                                        key={option.value}
                                        className={`flex cursor-pointer items-center gap-3 rounded-lg border px-4 py-3 transition ${
                                            data.certificate_type === option.value
                                                ? 'border-sterling-gold bg-sterling-gold-50 ring-1 ring-sterling-gold'
                                                : 'border-slate-200 hover:border-slate-300'
                                        }`}
                                    >
                                        <input
                                            type="radio"
                                            name="certificate_type"
                                            value={option.value}
                                            checked={data.certificate_type === option.value}
                                            onChange={() => handleCertificateTypeChange(option.value)}
                                            className="text-sterling-green focus:ring-sterling-gold"
                                        />
                                        <span className="text-sm font-medium text-slate-800">{option.label}</span>
                                    </label>
                                ))}
                            </div>
                            {errors.certificate_type && (
                                <p className="text-sm text-red-600">{errors.certificate_type}</p>
                            )}
                            <div>
                                <p className="mb-2 text-sm font-medium text-slate-700">Party type</p>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {partyTypeOptions.map((option) => (
                                        <label
                                            key={option.value}
                                            className={`flex items-center gap-3 rounded-lg border px-4 py-3 transition ${
                                                data.party_type === option.value
                                                    ? 'border-sterling-gold bg-sterling-gold-50 ring-1 ring-sterling-gold'
                                                    : 'border-slate-200 hover:border-slate-300'
                                            } ${isCarCertificate && option.value === 'private' ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'}`}
                                        >
                                            <input
                                                type="radio"
                                                name="party_type"
                                                value={option.value}
                                                checked={data.party_type === option.value}
                                                onChange={() => setData('party_type', option.value)}
                                                disabled={isCarCertificate && option.value === 'private'}
                                                className="text-sterling-green focus:ring-sterling-gold"
                                            />
                                            <span className="text-sm font-medium text-slate-800">{option.label}</span>
                                        </label>
                                    ))}
                                </div>
                                {errors.party_type && (
                                    <p className="mt-2 text-sm text-red-600">{errors.party_type}</p>
                                )}
                            </div>
                            <div>
                                <label
                                    className="flex items-center gap-3 rounded-lg border border-slate-200 px-4 py-3 cursor-pointer"
                                >
                                    <input
                                        type="checkbox"
                                        checked={Boolean(data.include_endorsement_number)}
                                        onChange={(e) => handleEndorsementToggle(e.target.checked)}
                                        className="rounded border-slate-300 text-sterling-green focus:ring-sterling-gold disabled:cursor-not-allowed"
                                    />
                                    <span className="text-sm font-medium text-slate-800">Include endorsement number</span>
                                </label>
                            </div>
                            {data.include_endorsement_number && (
                                <>
                                    <TextField
                                        label="Endorsement Number"
                                        value={data.endorsement_number}
                                        onChange={(e) => setData('endorsement_number', e.target.value)}
                                        placeholder="For [[Endorsement No.]] in the confirmation template"
                                        error={errors.endorsement_number}
                                        required
                                    />
                                    <TextField
                                        label="Extension Period Start (For CAR Confirmations)"
                                        type="date"
                                        value={data.extension_period_start}
                                        onChange={(e) => setData('extension_period_start', e.target.value)}
                                        error={errors.extension_period_start}
                                        required
                                    />
                                    <TextField
                                        label="Validity Extension (For CAR Confirmations)"
                                        value={data.validity_extension}
                                        onChange={(e) => setData('validity_extension', e.target.value)}
                                        placeholder="e.g. No. 3"
                                        error={errors.validity_extension}
                                    />
                                </>
                            )}
                            <div className="rounded-lg border border-slate-200 px-4 py-3">
                                <div className="space-y-3">
                                    <label className="flex items-center gap-3 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={data.require_notary}
                                            onChange={(e) => setData('require_notary', e.target.checked)}
                                            className="rounded border-slate-300 text-sterling-green focus:ring-sterling-gold"
                                        />
                                        <span className="text-sm font-medium text-slate-800">Require notary in the confirmation</span>
                                    </label>
                                    <label className="flex items-center gap-3 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={!data.require_notary}
                                            onChange={() => setData('require_notary', false)}
                                            className="rounded border-slate-300 text-sterling-green focus:ring-sterling-gold"
                                        />
                                        <span className="text-sm font-medium text-slate-800">Do not include Notary</span>
                                    </label>
                                </div>
                                <p className="mt-2 text-xs text-slate-500">
                                    Choose whether the approver should include a notary in the confirmation document.
                                </p>
                                <InputError message={errors.branch_balance} className="mt-2" />
                            </div>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Obligee</h2>
                            <EditableCombobox
                                label="Obligee"
                                value={data.obligee_id}
                                textValue={data.obligee_name}
                                onChange={(id) => setData('obligee_id', id)}
                                onTextChange={(text) => {
                                    setData((current) => ({
                                        ...current,
                                        obligee_name: text,
                                        obligee_id:
                                            text.trim().toLowerCase() ===
                                            (current.obligee_name || '').trim().toLowerCase()
                                                ? current.obligee_id
                                                : '',
                                    }));
                                }}
                                onOptionSelect={handleObligeeSelect}
                                searchUrl={route('api.obligees.index')}
                                placeholder="Type to search or enter an obligee name…"
                                error={errors.obligee_id || errors.obligee_name}
                                required
                                initialOption={obligeeInitial}
                            />
                            <p className="text-xs text-slate-500">
                                Select from KYC search results to auto-fill address details, or type a name directly.
                            </p>
                            {addressLines.map((line, index) => (
                                <div key={`address-line-${index}`} className="space-y-4">
                                    {index > 0 && (
                                        <div className="flex justify-end">
                                            <button
                                                type="button"
                                                onClick={() => removeAddressLine(index)}
                                                className="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
                                            >
                                                Remove address line
                                            </button>
                                        </div>
                                    )}
                                    <TextField
                                        label={index === 0 ? 'Business address' : `Business address (Line ${index + 1})`}
                                        value={line.address}
                                        onChange={(e) => updateAddressLine(index, 'address', e.target.value)}
                                        error={index === 0 ? errors.address_1 : undefined}
                                    />
                                    <TextField
                                        label={index === 0 ? 'Business CTM (City, Town, or Municipality)' : `Business CTM (City, Town, or Municipality) (Line ${index + 1})`}
                                        value={line.ctm}
                                        onChange={(e) => updateAddressLine(index, 'ctm', e.target.value)}
                                        error={index === 0 ? errors.address_2 : undefined}
                                    />
                                    <TextField
                                        label={index === 0 ? 'Business province' : `Business province (Line ${index + 1})`}
                                        value={line.province}
                                        onChange={(e) => updateAddressLine(index, 'province', e.target.value)}
                                        error={index === 0 ? errors.address_3 : undefined}
                                    />
                                </div>
                            ))}
                            <div>
                                <button
                                    type="button"
                                    onClick={addAddressLine}
                                    disabled={addressLines.length >= 3}
                                    className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    Add address line
                                </button>
                            </div>
                        </section>

                        <section className="grid gap-4 sm:grid-cols-2">
                            {isCarCertificate ? (
                                <>
                                    <div className="sm:col-span-2">
                                        <TextField
                                            label="CAR"
                                            value={data.car}
                                            onChange={(e) => setData('car', e.target.value)}
                                            placeholder={`CAR-${requesterBranchCode || 'MKT'}-0072056`}
                                            error={errors.car}
                                            required
                                        />
                                    </div>
                                    <div className="sm:col-span-2">
                                        <TextField
                                            label="Authorized Representative"
                                            value={data.authorized_representative}
                                            onChange={(e) => setData('authorized_representative', e.target.value)}
                                            error={errors.authorized_representative}
                                            required
                                        />
                                    </div>
                                </>
                            ) : (
                                <>
                                    <EditableCombobox
                                        label="Bond Type"
                                        value={data.bond_type_id}
                                        textValue={data.bond_type_label}
                                        onChange={(id) => setData('bond_type_id', id)}
                                        onTextChange={(text) => handleBondTypeChange({ target: { value: text } })}
                                        onOptionSelect={(option) => handleBondTypeChange({ target: { value: option.label } })}
                                        localOptions={bondTypeOptions.map((option) => ({
                                            id: option.value,
                                            label: option.label,
                                            code: option.code,
                                        }))}
                                        placeholder="Type or select bond type…"
                                        error={errors.bond_type_id}
                                        required
                                    />
                                    <TextField
                                        label="Branch Code"
                                        value={data.branch_code}
                                        onChange={(e) => {
                                            const nextBranchCode = e.target.value.toUpperCase().replace(/[^A-Z]/g, '');
                                            setData((current) => ({
                                                ...current,
                                                branch_code: nextBranchCode,
                                                bond_number: buildBondValue(
                                                    selectedBondTypeLabel,
                                                    nextBranchCode,
                                                    bondTypeBondNumber,
                                                ),
                                            }));
                                        }}
                                        readOnly={isRequesterRole}
                                        className={`uppercase ${isRequesterRole ? 'bg-slate-50' : ''}`}
                                    />
                                    <TextField
                                        label="Bond Number"
                                        value={bondTypeBondNumber}
                                        readOnly
                                        className="bg-slate-50"
                                        error={errors.bond_type_id}
                                        required
                                    />
                                    <div className="sm:col-span-2">
                                        <TextAreaField
                                            label="Bond"
                                            value={data.bond_number}
                                            onChange={(e) => setData('bond_number', e.target.value.toUpperCase())}
                                            placeholder="[[Bond Type]] NO. [[Bond Number]]-[[Branch Code]]-"
                                            rows={3}
                                            className="min-h-[96px] resize-y text-sm font-medium uppercase tracking-wide text-slate-700"
                                        />
                                    </div>
                                </>
                            )}
                            <div className="sm:col-span-2">
                                <EditableCombobox
                                    label="Principal"
                                    value={data.principal_id}
                                    textValue={data.principal_name}
                                    onChange={(id) => setData('principal_id', id)}
                                    onTextChange={(text) => {
                                        setData((current) => ({
                                            ...current,
                                            principal_name: text,
                                            principal_id:
                                                text.trim().toLowerCase() ===
                                                (current.principal_name || '').trim().toLowerCase()
                                                    ? current.principal_id
                                                    : '',
                                        }));
                                    }}
                                    onOptionSelect={(option) => {
                                        setData((current) => ({
                                            ...current,
                                            principal_id: option.id,
                                            principal_name: option.label || option.company_name || '',
                                        }));
                                    }}
                                    searchUrl={route('api.principals.index')}
                                    placeholder="Type to search or enter a principal name…"
                                    error={errors.principal_id || errors.principal_name}
                                    required
                                    initialOption={principalInitial}
                                />
                            </div>
                            <TextField
                                label="Date"
                                type="date"
                                value={data.request_date}
                                onChange={(e) => setData('request_date', e.target.value)}
                                error={errors.request_date}
                                required
                            />
                            <TextField
                                label="Date in words"
                                value={requestDateInWords}
                                readOnly
                                className="bg-slate-50"
                            />
                            <TextField
                                label="Amount (PHP)"
                                type="text"
                                inputMode="decimal"
                                autoComplete="off"
                                value={formatAmountDisplay(data.amount)}
                                onChange={(e) => setData('amount', parseAmountInput(e.target.value))}
                                error={errors.amount}
                                required
                            />
                            <div className="sm:col-span-2">
                                <TextAreaField
                                    label="Amount in words"
                                    value={amountWords}
                                    readOnly
                                    rows={4}
                                    className="bg-slate-50"
                                    error={errors.amount_in_words}
                                />
                            </div>
                            <div className="sm:col-span-2">
                                <TextAreaField
                                    label="Project name"
                                    value={data.project_name}
                                    onChange={(e) => setData('project_name', e.target.value)}
                                    rows={2}
                                    className="min-h-[44px] resize-y"
                                    error={errors.project_name}
                                />
                            </div>
                            <TextField
                                label="Date issued"
                                type="date"
                                value={data.date_issued}
                                onChange={(e) => setData('date_issued', e.target.value)}
                                error={errors.date_issued}
                            />
                            <TextField
                                label="Date issued in words"
                                value={dateIssuedInWords}
                                readOnly
                                className="bg-slate-50"
                            />
                            <TextField
                                label="Inception date"
                                type="date"
                                value={data.inception_date}
                                onChange={(e) => setData('inception_date', e.target.value)}
                                error={errors.inception_date}
                                required={!isCarCertificate}
                            />
                            <TextField
                                label="Inception date in words"
                                value={inceptionDateInWords}
                                readOnly
                                className="bg-slate-50"
                            />
                            <TextField
                                label="Attention"
                                value={data.attention}
                                onChange={(e) => setData('attention', e.target.value)}
                                error={errors.attention}
                            />
                            <div className="sm:col-span-2">
                                <TextAreaField
                                    label="Validity (eg. June 30, 2026 - June 30, 2027 or Statement)"
                                    value={data.expiry_date}
                                    onChange={(e) => setData('expiry_date', e.target.value)}
                                    placeholder="e.g. June 14, 2026 or until fully recouped and liquidated is valid"
                                    rows={2}
                                    className="min-h-[44px] resize-y"
                                    error={errors.expiry_date}
                                    required
                                />
                            </div>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Supporting documents</h2>
                            <p className="text-sm text-slate-500">
                                Upload up to 5 files (PDF, JPG, JPEG, or PNG). Each file may be up to 15 MB.
                            </p>
                            {visibleSupportingDocuments.length > 0 && (
                                <ul className="space-y-2 rounded-lg border border-slate-200 p-3">
                                    {visibleSupportingDocuments.map((document) => (
                                        <li key={document.path} className="flex flex-wrap items-center justify-between gap-2 text-sm">
                                            <a
                                                href={document.url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="text-sterling-green hover:underline"
                                            >
                                                {document.name}
                                            </a>
                                            {isEdit && (
                                                <button
                                                    type="button"
                                                    onClick={() => toggleRemoveSupportingDocument(document.path)}
                                                    className="text-red-600 hover:underline"
                                                >
                                                    Remove
                                                </button>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {remainingSupportingDocumentSlots > 0 && (
                                <div>
                                    <label htmlFor="supporting_documents" className="block text-sm font-medium text-slate-700">
                                        {isEdit ? 'Add supporting documents (optional)' : 'Supporting documents (optional)'}
                                    </label>
                                    <input
                                        id="supporting_documents"
                                        name="supporting_documents[]"
                                        type="file"
                                        multiple
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        onChange={handleSupportingDocumentsChange}
                                        className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm file:mr-3 file:rounded file:border-0 file:bg-sterling-gold file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-sterling-green-darker"
                                    />
                                    <p className="mt-1 text-xs text-slate-500">
                                        {remainingSupportingDocumentSlots} file slot
                                        {remainingSupportingDocumentSlots === 1 ? '' : 's'} remaining.
                                    </p>
                                </div>
                            )}
                            {data.supporting_documents?.length > 0 && (
                                <ul className="space-y-1 text-sm text-slate-700">
                                    {data.supporting_documents.map((file) => (
                                        <li key={`${file.name}-${file.lastModified}`}>{file.name}</li>
                                    ))}
                                </ul>
                            )}
                            <InputError message={errors.supporting_documents} className="mt-2" />
                            <InputError message={errors['supporting_documents.0']} className="mt-2" />
                            <InputError message={errors.branch_balance} className="mt-2" />
                        </section>

                        <div className="flex gap-3">
                            <PrimaryButton disabled={processing || hasInsufficientBranchFund}>
                                {isEdit ? 'Update' : 'Create'}
                            </PrimaryButton>
                            <Link href={route('bond-requests.index')}>
                                <SecondaryButton type="button">Cancel</SecondaryButton>
                            </Link>
                        </div>
                    </form>
                </CardBody>
            </Card>
        </AppLayout>
    );
}
