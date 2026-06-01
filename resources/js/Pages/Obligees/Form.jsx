import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import Card, { CardBody } from '@/Components/UI/Card';
import { TextAreaField, TextField } from '@/Components/UI/FormField';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Form({ obligee }) {
    const isEdit = Boolean(obligee?.id);
    const { data, setData, post, put, processing, errors } = useForm({
        company_name: obligee?.company_name || '',
        address: obligee?.address || '',
        contact_person: obligee?.contact_person || '',
        email: obligee?.email || '',
        phone_number: obligee?.phone_number || '',
    });

    const submit = (e) => {
        e.preventDefault();
        isEdit ? put(route('obligees.update', obligee.id)) : post(route('obligees.store'));
    };

    return (
        <AppLayout title={isEdit ? 'Edit Obligee' : 'New Obligee'}>
            <Head title={isEdit ? 'Edit Obligee' : 'New Obligee'} />
            <Card className="max-w-2xl">
                <CardBody>
                    <form onSubmit={submit} className="space-y-4">
                        <TextField label="Company Name" value={data.company_name} onChange={(e) => setData('company_name', e.target.value)} error={errors.company_name} required />
                        <TextAreaField label="Address" value={data.address} onChange={(e) => setData('address', e.target.value)} error={errors.address} />
                        <TextField label="Contact Person" value={data.contact_person} onChange={(e) => setData('contact_person', e.target.value)} error={errors.contact_person} />
                        <TextField label="Email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} error={errors.email} />
                        <TextField label="Phone Number" value={data.phone_number} onChange={(e) => setData('phone_number', e.target.value)} error={errors.phone_number} />
                        <div className="flex gap-3">
                            <PrimaryButton disabled={processing}>{isEdit ? 'Update' : 'Create'}</PrimaryButton>
                            <Link href={route('obligees.index')}><SecondaryButton type="button">Cancel</SecondaryButton></Link>
                        </div>
                    </form>
                </CardBody>
            </Card>
        </AppLayout>
    );
}
