import '../css/app.css';
import './bootstrap';

import { PageTransitionProvider } from '@/Contexts/PageTransitionContext';
import { ToastProvider } from '@/Contexts/ToastContext';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Sterling Bond System';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);
        root.render(
            <ToastProvider>
                <PageTransitionProvider>
                    <App {...props} />
                </PageTransitionProvider>
            </ToastProvider>,
        );
    },
    progress: {
        color: '#D99E1A',
    },
});
