import SterlingLogo from '@/Components/Brand/SterlingLogo';
import FluidBackground from '@/Components/Auth/FluidBackground';
import useLoginIntro from '@/hooks/useLoginIntro';

export default function GuestLayout({ children, unified = false }) {
    const playIntro = useLoginIntro();

    if (unified) {
        return (
            <div
                className={`auth-fluid-bg auth-fluid-bg--lively login-intro-scene flex min-h-[100dvh] items-center justify-center px-4 py-[max(1.5rem,env(safe-area-inset-top))] pb-[max(1.5rem,env(safe-area-inset-bottom))] sm:px-6 ${playIntro ? 'login-intro-scene--play' : ''}`}
            >
                <FluidBackground lively />
                <div
                    className={`relative z-10 w-full max-w-md overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-sterling-gold/25 ${playIntro ? 'login-intro-card--play' : ''}`}
                >
                    <div
                        className={`login-intro-header border-b border-slate-100 bg-sterling-green-50 px-5 py-5 text-center sm:px-8 sm:py-6 ${playIntro ? 'login-intro-header--play' : ''}`}
                    >
                        <SterlingLogo size="lg" className="mx-auto" />
                        <p className="mt-2 text-[0.65rem] font-medium uppercase leading-snug tracking-wide text-sterling-green/80 sm:mt-3 sm:text-xs">
                            Bond Notarization & Certificate Management
                        </p>
                    </div>
                    <div
                        className={`px-5 py-5 sm:px-8 sm:py-6 ${playIntro ? 'login-intro-body--play' : ''}`}
                    >
                        {children}
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="flex min-h-[100dvh] flex-col items-center justify-center bg-sterling-green px-4 py-[max(1.5rem,env(safe-area-inset-top))] pb-[max(1.5rem,env(safe-area-inset-bottom))]">
            <div className="mb-6 max-w-md text-center sm:mb-8">
                <SterlingLogo size="xl" className="mx-auto" />
                <p className="mt-3 text-xs text-white/70 sm:mt-4 sm:text-sm">
                    Bond Notarization & Certificate Management
                </p>
            </div>
            <div className="w-full max-w-md overflow-hidden rounded-xl bg-white p-6 shadow-xl ring-1 ring-sterling-gold/20 sm:p-8">
                {children}
            </div>
        </div>
    );
}
