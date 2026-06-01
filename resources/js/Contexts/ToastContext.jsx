import { router } from '@inertiajs/react';
import { createContext, useCallback, useContext, useEffect, useState } from 'react';

const ToastContext = createContext(null);

export function ToastProvider({ children }) {
    const [toasts, setToasts] = useState([]);

    const addToast = useCallback((message, type = 'success') => {
        const id = Date.now();
        setToasts((prev) => [...prev, { id, message, type }]);
        setTimeout(() => {
            setToasts((prev) => prev.filter((t) => t.id !== id));
        }, 4000);
    }, []);

    const dismiss = useCallback((id) => {
        setToasts((prev) => prev.filter((t) => t.id !== id));
    }, []);

    useEffect(() => {
        const handleFlash = (event) => {
            const flash = event.detail?.page?.props?.flash;
            if (flash?.success) addToast(flash.success, 'success');
            if (flash?.error) addToast(flash.error, 'error');
        };

        return router.on('success', handleFlash);
    }, [addToast]);

    return (
        <ToastContext.Provider value={{ addToast }}>
            {children}
            <ToastStack toasts={toasts} onDismiss={dismiss} />
        </ToastContext.Provider>
    );
}

function ToastStack({ toasts, onDismiss }) {
    if (!toasts.length) return null;

    return (
        <div className="fixed right-4 top-4 z-[100] flex w-full max-w-sm flex-col gap-2">
            {toasts.map((toast) => (
                <ToastItem key={toast.id} toast={toast} onDismiss={onDismiss} />
            ))}
        </div>
    );
}

function ToastItem({ toast, onDismiss }) {
    const styles =
        toast.type === 'error'
            ? 'border-red-200 bg-red-50 text-red-800'
            : 'border-emerald-200 bg-emerald-50 text-emerald-800';

    return (
        <div className={`flex items-start justify-between gap-2 rounded-lg border px-4 py-3 shadow-lg ${styles}`}>
            <p className="text-sm font-medium">{toast.message}</p>
            <button type="button" onClick={() => onDismiss(toast.id)} className="opacity-60 hover:opacity-100">
                ×
            </button>
        </div>
    );
}

export function useToast() {
    const context = useContext(ToastContext);
    if (!context) throw new Error('useToast must be used within ToastProvider');
    return context;
}
