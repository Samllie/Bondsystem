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
import { Head, Link, useForm } from '@inertiajs/react';
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

export default function Form({
    bondRequest,
    selectedPrincipal,
    selectedObligee,
    bondTypeOptions,
    certificateTypeOptions,
    supportingDocumentUrl,
    requesterBranchCode = '',
}) {
    const isEdit = Boolean(bondRequest?.id);

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
        supporting_document: null,
        certificate_type: bondRequest?.certificate_type?.value || bondRequest?.certificate_type || 'bond_certificate',
        has_endorsement: Boolean(bondRequest?.endorsement_number),
        endorsement_number: bondRequest?.endorsement_number || '',
        car: bondRequest?.car || buildCarValue(requesterBranchCode),
        authorized_representative: bondRequest?.authorized_representative || '',
        expiry_date: formatExpiryForForm(bondRequest?.expiry_date),
    });

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

        setData((current) => ({
            ...current,
            bond_type_id: selectedBondType?.value || '',
            bond_type_label: nextBondTypeLabel,
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

        if (isEdit) {
            // PHP can't parse multipart/form-data on PUT requests, so spoof the
            // method by POSTing with a _method field.
            transform((current) => ({ ...current, _method: 'put' }));
            post(route('bond-requests.update', bondRequest.id), options);
        } else {
            transform((current) => current);
            post(route('bond-requests.store'), options);
        }
    };

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

    const bondTypeSerial = useMemo(() => {
        if (selectedBondType?.bond_serial) {
            return selectedBondType.bond_serial;
        }

        if (bondRequest?.bondTypeMaster?.bond_serial) {
            return bondRequest.bondTypeMaster.bond_serial;
        }

        return '';
    }, [bondRequest, selectedBondType]);

    const bondDisplay = useMemo(
        () => buildBondValue(selectedBondTypeLabel, requesterBranchCode, bondTypeBondNumber, bondTypeSerial),
        [bondTypeBondNumber, bondTypeSerial, requesterBranchCode, selectedBondTypeLabel],
    );

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
        }));
    };

    const handleEndorsementToggle = (checked) => {
        setData((current) => ({
            ...current,
            has_endorsement: checked,
            endorsement_number: checked ? current.endorsement_number : '',
        }));
    };

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
                    <form onSubmit={submit} encType="multipart/form-data" className="space-y-6">
                        <section className="space-y-4">
                            <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Certificate request</h2>
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
                            <label className="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-4 py-3">
                                <input
                                    type="checkbox"
                                    checked={Boolean(data.has_endorsement)}
                                    onChange={(e) => handleEndorsementToggle(e.target.checked)}
                                    className="rounded border-slate-300 text-sterling-green focus:ring-sterling-gold"
                                />
                                <span className="text-sm font-medium text-slate-800">Include endorsement number</span>
                            </label>
                            {data.has_endorsement && (
                                <TextField
                                    label="Endorsement Number"
                                    value={data.endorsement_number}
                                    onChange={(e) => setData('endorsement_number', e.target.value)}
                                    placeholder="For [[Endorsement No.]] in the certificate template"
                                    error={errors.endorsement_number}
                                    required
                                />
                            )}
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
                                            bond_serial: option.bond_serial,
                                        }))}
                                        placeholder="Type or select bond type…"
                                        error={errors.bond_type_id}
                                        required
                                    />
                                    <TextField
                                        label="Branch Code"
                                        value={requesterBranchCode || ''}
                                        readOnly
                                        className="bg-slate-50 uppercase"
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
                                            value={bondDisplay}
                                            readOnly
                                            rows={2}
                                            className="min-h-[44px] resize-y bg-slate-50 py-2.5 text-sm font-medium tracking-wide text-slate-700"
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
                                    label="Expiry date or validity statement"
                                    value={data.expiry_date}
                                    onChange={(e) => setData('expiry_date', e.target.value)}
                                    placeholder="e.g. 2027-05-24 or until fully recouped and liquidated is valid"
                                    rows={2}
                                    className="min-h-[44px] resize-y"
                                    error={errors.expiry_date}
                                    required
                                />
                            </div>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Supporting document</h2>
                            {supportingDocumentUrl && (
                                <a
                                    href={supportingDocumentUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex text-sm text-sterling-green hover:underline"
                                >
                                    View current document →
                                </a>
                            )}
                            <div>
                                <label htmlFor="supporting_document" className="block text-sm font-medium text-slate-700">
                                    {isEdit ? 'Replace supporting document (optional)' : 'Supporting document (optional)'}
                                </label>
                                <input
                                    id="supporting_document"
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    onChange={(e) => setData('supporting_document', e.target.files[0] ?? null)}
                                    className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm file:mr-3 file:rounded file:border-0 file:bg-sterling-gold file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-sterling-green-darker"
                                />
                                <InputError message={errors.supporting_document} className="mt-2" />
                            </div>
                        </section>

                        <div className="flex gap-3">
                            <PrimaryButton disabled={processing}>{isEdit ? 'Update' : 'Create'}</PrimaryButton>
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
