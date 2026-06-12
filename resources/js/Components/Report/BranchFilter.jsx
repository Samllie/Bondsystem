import { SelectField } from '@/Components/UI/FormField';

export default function BranchFilter({ value, onChange, branchOptions, label = 'Branch', className = '' }) {
    if (!branchOptions?.length) {
        return null;
    }

    return (
        <SelectField
            label={label}
            value={value ?? ''}
            onChange={onChange}
            options={[{ value: '', label: 'All branches' }, ...branchOptions]}
            className={className}
        />
    );
}
