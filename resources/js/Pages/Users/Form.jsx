import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import Card, { CardBody } from '@/Components/UI/Card';
import BackLink from '@/Components/UI/BackLink';
import EditableCombobox from '@/Components/UI/EditableCombobox';
import { SelectField, TextField } from '@/Components/UI/FormField';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

export default function Form({ roleOptions, branchOptions }) {
    const roleSelectOptions = useMemo(
        () => [{ value: '', label: 'Select account level…' }, ...roleOptions],
        [roleOptions],
    );

    const branchComboboxOptions = useMemo(
        () =>
            branchOptions.map((option) => ({
                id: option.value,
                label: option.label,
                city: option.city,
                branch_code: option.branch_code,
            })),
        [branchOptions],
    );

    const [branchLabel, setBranchLabel] = useState('');

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
        role_id: '',
        branch_id: '',
        branch_code: '',
        branch_city: '',
        is_active: true,
    });

    const handleBranchSelect = (option) => {
        setData((current) => ({
            ...current,
            branch_id: option.id,
            branch_code: option.branch_code ?? current.branch_code,
            branch_city: option.city ?? '',
        }));
        setBranchLabel(option.label);
    };

    const submit = (e) => {
        e.preventDefault();

        post(route('users.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AppLayout title="Add User">
            <Head title="Add User" />

            <BackLink href={route('users.index')}>Back to Users</BackLink>

            <Card className="max-w-2xl">
                <CardBody>
                    <form onSubmit={submit} className="space-y-6">
                        <TextField
                            label="Name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            error={errors.name}
                            required
                        />

                        <TextField
                            label="Email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            error={errors.email}
                            required
                        />

                        <TextField
                            label="Phone"
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                            error={errors.phone}
                        />

                        <TextField
                            label="Password"
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            error={errors.password}
                            required
                            autoComplete="new-password"
                        />

                        <TextField
                            label="Confirm Password"
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            error={errors.password_confirmation}
                            required
                            autoComplete="new-password"
                        />

                        <SelectField
                            label="Account Level"
                            value={data.role_id}
                            onChange={(e) => setData('role_id', e.target.value)}
                            options={roleSelectOptions}
                            error={errors.role_id}
                            required
                        />

                        <EditableCombobox
                            label="Branch"
                            value={data.branch_id}
                            textValue={branchLabel}
                            onChange={(id) => setData('branch_id', id)}
                            onTextChange={setBranchLabel}
                            onOptionSelect={handleBranchSelect}
                            localOptions={branchComboboxOptions}
                            placeholder="Type or select a branch…"
                            error={errors.branch_id}
                        />

                        <TextField
                            label="Branch Code"
                            value={data.branch_code}
                            onChange={(e) =>
                                setData('branch_code', e.target.value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 3))
                            }
                            maxLength={3}
                            placeholder="e.g. MKT"
                            error={errors.branch_code}
                            className="uppercase"
                        />

                        <TextField
                            label="Branch City"
                            value={data.branch_city}
                            onChange={(e) => setData('branch_city', e.target.value)}
                            error={errors.branch_city}
                        />

                        <div className="flex items-center gap-3">
                            <input
                                id="is_active"
                                type="checkbox"
                                checked={data.is_active}
                                onChange={(e) => setData('is_active', e.target.checked)}
                                className="h-4 w-4 rounded border-slate-300 text-sterling-gold"
                            />
                            <label htmlFor="is_active" className="text-sm font-medium text-slate-700">
                                Active account
                            </label>
                        </div>

                        <div className="flex gap-3">
                            <PrimaryButton disabled={processing}>Create User</PrimaryButton>
                            <Link href={route('users.index')}>
                                <SecondaryButton type="button">Cancel</SecondaryButton>
                            </Link>
                        </div>
                    </form>
                </CardBody>
            </Card>
        </AppLayout>
    );
}
