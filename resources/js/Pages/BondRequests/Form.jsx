import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import Card, { CardBody } from '@/Components/UI/Card';
import EditableCombobox from '@/Components/UI/EditableCombobox';
import { SelectField, TextAreaField, TextField } from '@/Components/UI/FormField';
import SearchableSelect from '@/Components/UI/SearchableSelect';
import AppLayout from '@/Layouts/AppLayout';
import { amountInWords } from '@/lib/amountInWords';
import { formatAmountDisplay, parseAmountInput } from '@/lib/formatAmount';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo } from 'react';

function formatDate(value) {
    if (!value) {
        return '';
    }

    return String(value).substring(0, 10);
}

function todayIso() {
    return new Date().toISOString().substring(0, 10);
}

export default function Form({
    bondRequest,
    selectedPrincipal,
    selectedObligee,
    bondTypeOptions,
    signatoryOptions,
    notaryOptions,
    isRequester = false,
}) {
    const isEdit = Boolean(bondRequest?.id);

    const { data, setData, post, put, processing, errors } = useForm({
        obligee_id: bondRequest?.obligee_id || '',
        obligee_name: bondRequest?.obligee_name || selectedObligee?.company_name || '',
        address_1: bondRequest?.address_1 || '',
        address_2: bondRequest?.address_2 || '',
        address_3: bondRequest?.address_3 || '',
        bond_number: bondRequest?.bond_number || '',
        bond_type_id: bondRequest?.bond_type_id || '',
        principal_id: bondRequest?.principal_id || '',
        request_date: formatDate(bondRequest?.request_date) || todayIso(),
        amount: bondRequest?.amount || '',
        project_name: bondRequest?.project_name || '',
        expiry_date: formatDate(bondRequest?.expiry_date),
        signatory_id: bondRequest?.signatory_id || '',
        signatory_position: bondRequest?.signatory_position || '',
        notary_id: bondRequest?.notary_id || '',
        doc_no: bondRequest?.doc_no || '',
        page_no: bondRequest?.page_no || '',
        book_no: bondRequest?.book_no || '',
        series_year: bondRequest?.series_year || '',
        description: bondRequest?.description || '',
        remarks: bondRequest?.remarks || '',
    });

    const bondTypeSelectOptions = useMemo(
        () => [{ value: '', label: 'Select bond type…' }, ...bondTypeOptions],
        [bondTypeOptions],
    );

    const signatorySelectOptions = useMemo(
        () => [{ value: '', label: 'Select signatory…' }, ...signatoryOptions],
        [signatoryOptions],
    );

    const notarySelectOptions = useMemo(
        () => [{ value: '', label: 'Select notary…' }, ...notaryOptions],
        [notaryOptions],
    );

    const amountWords = useMemo(() => amountInWords(data.amount), [data.amount]);

    const handleSignatoryChange = (event) => {
        const id = event.target.value;
        setData('signatory_id', id);

        const selected = signatoryOptions.find((option) => String(option.value) === String(id));
        setData('signatory_position', selected?.position || '');
    };

    const handleObligeeSelect = (option) => {
        setData((current) => ({
            ...current,
            address_1: option?.business_address ?? '',
            address_2: option?.business_ctm ?? '',
            address_3: option?.business_province ?? '',
        }));
    };

    const submit = (e) => {
        e.preventDefault();

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
                            <TextField
                                label="Business address"
                                value={data.address_1}
                                onChange={(e) => setData('address_1', e.target.value)}
                                error={errors.address_1}
                            />
                            <TextField
                                label="Business CTM"
                                value={data.address_2}
                                onChange={(e) => setData('address_2', e.target.value)}
                                error={errors.address_2}
                            />
                            <TextField
                                label="Business province"
                                value={data.address_3}
                                onChange={(e) => setData('address_3', e.target.value)}
                                error={errors.address_3}
                            />
                        </section>

                        <section className="grid gap-4 sm:grid-cols-2">
                            <TextField
                                label="Bond Number"
                                value={data.bond_number}
                                onChange={(e) => setData('bond_number', e.target.value)}
                                error={errors.bond_number}
                                required
                            />
                            <SelectField
                                label="Bond Type"
                                value={data.bond_type_id}
                                onChange={(e) => setData('bond_type_id', e.target.value)}
                                options={bondTypeSelectOptions}
                                error={errors.bond_type_id}
                                required
                            />
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
                                <TextField
                                    label="Project name"
                                    value={data.project_name}
                                    onChange={(e) => setData('project_name', e.target.value)}
                                    error={errors.project_name}
                                />
                            </div>
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
                                required
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
                                required
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
                                value={data.book_no}
                                onChange={(e) => setData('book_no', e.target.value)}
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

                        {!isRequester && (
                            <section className="space-y-4">
                                <TextAreaField
                                    label="Description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    error={errors.description}
                                />
                                <TextAreaField
                                    label="Remarks"
                                    value={data.remarks}
                                    onChange={(e) => setData('remarks', e.target.value)}
                                    error={errors.remarks}
                                />
                            </section>
                        )}

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
