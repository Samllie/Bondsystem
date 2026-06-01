import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';

export default function ConfirmModal({
    show,
    onClose,
    onConfirm,
    title = 'Confirm',
    message,
    confirmLabel = 'Confirm',
    processing = false,
    danger = false,
}) {
    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <div className="p-6">
                <h3 className="text-lg font-semibold text-sterling-green">{title}</h3>
                <p className="mt-2 text-sm text-slate-600">{message}</p>
                <div className="mt-6 flex justify-end gap-3">
                    <SecondaryButton onClick={onClose}>Cancel</SecondaryButton>
                    <PrimaryButton
                        onClick={onConfirm}
                        disabled={processing}
                        className={danger ? 'bg-red-600 hover:bg-red-700 focus:ring-red-500' : ''}
                    >
                        {processing ? 'Processing...' : confirmLabel}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    );
}
