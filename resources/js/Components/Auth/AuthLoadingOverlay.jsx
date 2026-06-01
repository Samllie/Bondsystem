import SterlingLogo from '@/Components/Brand/SterlingLogo';

export default function AuthLoadingOverlay({ message = 'Authenticating' }) {
    return (
        <div
            className="auth-loading-overlay fixed inset-0 z-[100] flex items-center justify-center px-4 pb-[env(safe-area-inset-bottom)] pt-[env(safe-area-inset-top)] sm:px-6"
            role="status"
            aria-live="polite"
            aria-busy="true"
            aria-label={message}
        >
            <div className="auth-loading-overlay__panel flex max-w-sm flex-col items-center text-center">
                <SterlingLogo
                    size="md"
                    className="auth-loading-overlay__logo rounded-lg bg-white px-3 py-2 shadow-lg sm:px-4"
                />

                <div
                    className="auth-loading-overlay__spinner mt-5 h-9 w-9 rounded-full border-[3px] border-sterling-gold/25 border-t-sterling-gold sm:mt-6 sm:h-10 sm:w-10"
                    aria-hidden="true"
                />

                <p className="mt-5 font-serif text-base font-semibold text-white sm:mt-6 sm:text-lg">
                    {message}
                    <span className="auth-loading-overlay__dots" aria-hidden="true">
                        <span>.</span>
                        <span>.</span>
                        <span>.</span>
                    </span>
                </p>

                <p className="mt-2 max-w-xs text-pretty text-xs text-white/70 sm:text-sm">
                    Please wait while we verify your credentials
                </p>
            </div>
        </div>
    );
}
