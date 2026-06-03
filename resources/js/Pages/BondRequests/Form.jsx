import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import Card, { CardBody } from '@/Components/UI/Card';
import EditableCombobox from '@/Components/UI/EditableCombobox';
import { SelectField, TextAreaField, TextField } from '@/Components/UI/FormField';
import SearchableSelect from '@/Components/UI/SearchableSelect';
import AppLayout from '@/Layouts/AppLayout';
import { amountInWords } from '@/lib/amountInWords';
import { formatAmountDisplay, parseAmountInput } from '@/lib/formatAmount';
import { formatBookNoDisplay, formatBookNoInput } from '@/lib/romanNumerals';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

function formatDate(value) {
    if (!value) {
        return '';
    }

    return String(value).substring(0, 10);
}

function todayIso() {
    return new Date().toISOString().substring(0, 10);
}

function generateBondSerial(seedId = null) {
    if (seedId) {
        return `GEN-${String(seedId).padStart(7, '0')}`;
    }

    const serial = Math.floor(Math.random() * 10000000);

    return `GEN-${String(serial).padStart(7, '0')}`;
}

function buildBondValue(bondTypeLabel, bondNumber, serial) {
    if (!bondTypeLabel || !bondNumber || !serial) {
        return '';
    }

    return `${bondTypeLabel} NO. ${bondNumber}-${serial}`;
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

function daySuffix(day) {
    const remainderTen = day % 10;
    const remainderHundred = day % 100;

    if (remainderTen === 1 && remainderHundred !== 11) {
        return 'st';
    }

    if (remainderTen === 2 && remainderHundred !== 12) {
        return 'nd';
    }

    if (remainderTen === 3 && remainderHundred !== 13) {
        return 'rd';
    }

    return 'th';
}

function formatDateInWords(value) {
    if (!value) {
        return '';
    }

    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const day = date.getDate();
    const month = date.toLocaleString('en-US', { month: 'long' });
    const year = date.getFullYear();

    return `${day}${daySuffix(day)} day of ${month}, ${year}`;
}

export default function Form({
    bondRequest,
    selectedPrincipal,
    selectedObligee,
    bondTypeOptions,
    signatoryOptions,
    notaryOptions,
}) {
    const isEdit = Boolean(bondRequest?.id);
    const generatedBondSerial = generateBondSerial(bondRequest?.id ?? null);

    const { data, setData, post, put, processing, errors, transform } = useForm({
        obligee_id: bondRequest?.obligee_id || '',
        obligee_name: bondRequest?.obligee_name || selectedObligee?.company_name || '',
        address_1: bondRequest?.address_1 || '',
        address_2: bondRequest?.address_2 || '',
        address_3: bondRequest?.address_3 || '',
        bond_number: bondRequest?.bond_number || '',
        bond_type_id: bondRequest?.bond_type_id || '',
        bond_type_label: bondRequest?.bondTypeMaster?.name || '',
        bond: buildBondValue(bondRequest?.bondTypeMaster?.name || '', bondRequest?.bond_number || '', generatedBondSerial),
        principal_id: bondRequest?.principal_id || '',
        request_date: formatDate(bondRequest?.request_date) || todayIso(),
        amount: bondRequest?.amount || '',
        project_name: bondRequest?.project_name || '',
        date_issued: formatDate(bondRequest?.date_issued),
        expiry_date: formatDate(bondRequest?.expiry_date),
        signatory_id: bondRequest?.signatory_id || '',
        signatory_position: bondRequest?.signatory_position || '',
        notary_id: bondRequest?.notary_id || '',
        doc_no: bondRequest?.doc_no || '',
        page_no: bondRequest?.page_no || '',
        book_no: formatBookNoDisplay(bondRequest?.book_no || ''),
        series_year: bondRequest?.series_year || '',
    });

    const [bookNoDraft, setBookNoDraft] = useState(() => formatBookNoDisplay(bondRequest?.book_no || ''));
    const bookNoDebounceRef = useRef(null);

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

    const signatorySelectOptions = useMemo(
        () => [{ value: '', label: 'Select signatory…' }, ...signatoryOptions],
        [signatoryOptions],
    );

    const notarySelectOptions = useMemo(
        () => [{ value: '', label: 'Select notary…' }, ...notaryOptions],
        [notaryOptions],
    );

    const amountWords = useMemo(() => amountInWords(data.amount), [data.amount]);
    const requestDateInWords = useMemo(() => formatDateInWords(data.request_date), [data.request_date]);
    const dateIssuedInWords = useMemo(() => formatDateInWords(data.date_issued), [data.date_issued]);

    useEffect(() => () => clearTimeout(bookNoDebounceRef.current), []);

    const handleBookNoChange = (event) => {
        const value = event.target.value;
        setBookNoDraft(value);

        clearTimeout(bookNoDebounceRef.current);
        bookNoDebounceRef.current = setTimeout(() => {
            const formatted = formatBookNoInput(value);
            setBookNoDraft(formatted);
            setData('book_no', formatted);
        }, 750);
    };

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

    const handleSignatoryChange = (event) => {
        const id = event.target.value;
        const selected = signatoryOptions.find((option) => String(option.value) === String(id));

        setData((current) => ({
            ...current,
            signatory_id: id,
            signatory_position: id ? (selected?.position || '') : '',
        }));
    };

    const handleBondTypeChange = (event) => {
        const nextBondTypeLabel = event.target.value;
        const selectedBondType = bondTypeOptions.find(
            (option) => option.label.toLowerCase() === nextBondTypeLabel.toLowerCase(),
        );
        const nextBondNumber = selectedBondType?.code;

        setData((current) => ({
            ...current,
            bond_type_id: selectedBondType?.value || '',
            bond_type_label: nextBondTypeLabel,
            bond_number: typeof nextBondNumber === 'string' ? nextBondNumber : current.bond_number,
            bond: buildBondValue(
                nextBondTypeLabel,
                typeof nextBondNumber === 'string' ? nextBondNumber : current.bond_number,
                generatedBondSerial,
            ),
        }));
    };

    const handleObligeeSelect = (option) => {
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

        clearTimeout(bookNoDebounceRef.current);
        const formattedBookNo = formatBookNoInput(bookNoDraft);
        setBookNoDraft(formattedBookNo);

        transform((current) => ({
            ...current,
            book_no: formattedBookNo,
        }));

        if (isEdit) {
            put(route('bond-requests.update', bondRequest.id));
        } else {
            post(route('bond-requests.store'));
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

    return (
        <AppLayout title={isEdit ? 'Edit Bond Request' : 'New Bond Request'}>
            <Head title={isEdit ? 'Edit Bond Request' : 'New Bond Request'} />

            <Card className="max-w-3xl">
                <CardBody>
                    <form onSubmit={submit} className="space-y-6">
                        <section className="space-y-4">
                            <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Obligee</h2>
                            <EditableCombobox
                                label="Obligee"
                                value={data.obligee_id}
                                textValue={data.obligee_name}
                                onChange={(id) => setData('obligee_id', id)}
                                onTextChange={(text) => setData('obligee_name', text)}
                                onOptionSelect={handleObligeeSelect}
                                searchUrl={route('api.obligees.index')}
                                placeholder="Type or select an obligee…"
                                error={errors.obligee_id || errors.obligee_name}
                                required
                                initialOption={obligeeInitial}
                            />
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
                                        label={index === 0 ? 'Business CTM' : `Business CTM (Line ${index + 1})`}
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
                                label="Bond Number"
                                value={data.bond_number}
                                onChange={(e) => {
                                    const nextBondNumber = e.target.value;
                                    setData((current) => ({
                                        ...current,
                                        bond_number: nextBondNumber,
                                        bond: buildBondValue(selectedBondTypeLabel, nextBondNumber, generatedBondSerial),
                                    }));
                                }}
                                error={errors.bond_number}
                                required
                            />
                            <div className="sm:col-span-2">
                                <TextAreaField
                                    label="Bond"
                                    value={data.bond}
                                    onChange={(e) => setData('bond', e.target.value)}
                                    rows={2}
                                    className="min-h-[44px] resize-y bg-slate-50 py-2.5 text-sm font-medium tracking-wide text-slate-700"
                                />
                            </div>
                            <div className="sm:col-span-2">
                                <SearchableSelect
                                    label="Principal"
                                    value={data.principal_id}
                                    onChange={(id) => setData('principal_id', id)}
                                    searchUrl={route('api.principals.index')}
                                    placeholder="Type to search principals…"
                                    error={errors.principal_id}
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
                                label="Expiry date"
                                type="date"
                                value={data.expiry_date}
                                onChange={(e) => setData('expiry_date', e.target.value)}
                                error={errors.expiry_date}
                                required
                            />
                            <SelectField
                                label="Signatory"
                                value={data.signatory_id}
                                onChange={handleSignatoryChange}
                                options={signatorySelectOptions}
                                error={errors.signatory_id}
                            />
                            <TextField
                                label="Position"
                                value={data.signatory_position}
                                readOnly
                                className="bg-slate-50"
                                error={errors.signatory_position}
                            />
                            <SelectField
                                label="Notary"
                                value={data.notary_id}
                                onChange={(e) => setData('notary_id', e.target.value)}
                                options={notarySelectOptions}
                                error={errors.notary_id}
                            />
                            <TextField
                                label="Doc No."
                                value={data.doc_no}
                                onChange={(e) => setData('doc_no', e.target.value)}
                                error={errors.doc_no}
                            />
                            <TextField
                                label="Page No."
                                value={data.page_no}
                                onChange={(e) => setData('page_no', e.target.value)}
                                error={errors.page_no}
                            />
                            <TextField
                                label="Book No."
                                value={bookNoDraft}
                                onChange={handleBookNoChange}
                                placeholder="e.g. V"
                                error={errors.book_no}
                            />
                            <TextField
                                label="Series year"
                                value={data.series_year}
                                onChange={(e) => setData('series_year', e.target.value)}
                                error={errors.series_year}
                                maxLength={4}
                            />
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
