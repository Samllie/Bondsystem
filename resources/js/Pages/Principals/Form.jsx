import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import Card, { CardBody } from '@/Components/UI/Card';
import BackLink from '@/Components/UI/BackLink';
import { TextAreaField, TextField } from '@/Components/UI/FormField';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Form({ principal }) {
    const isEdit = Boolean(principal?.id);
    const { data, setData, post, put, processing, errors } = useForm({
        company_name: principal?.company_name || '',
        address: principal?.address || '',
        contact_person: principal?.contact_person || '',
        email: principal?.email || '',
        phone_number: principal?.phone_number || '',
    });

    const submit = (e) => {
        e.preventDefault();
        isEdit ? put(route('principals.update', principal.id)) : post(route('principals.store'));
    };

    return (
        <AppLayout title={isEdit ? 'Edit Principal' : 'New Principal'}>
            <Head title={isEdit ? 'Edit Principal' : 'New Principal'} />
            <BackLink href={route('principals.index')}>Back to Principals</BackLink>
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
                            <Link href={route('principals.index')}>
                                <SecondaryButton type="button">Cancel</SecondaryButton>
                            </Link>
                        </div>
                    </form>
                </CardBody>
            </Card>
        </AppLayout>
    );
}
