import SterlingLogo from '@/Components/Brand/SterlingLogo';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

const Icon = ({ name, className = 'h-5 w-5' }) => {
    const paths = {
        dashboard: 'M4 6h16M4 12h8m-8 6h16',
        document:
            'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        building:
            'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
        users: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
        cog: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
        'credit-card': 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
        list: 'M4 6h16M4 10h16M4 14h16M4 18h16',
        history: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        inbox: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4',
        money: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
        certificate:
            'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        chevron: 'M19 9l-7 7-7-7',
        close: 'M6 18L18 6M6 6l12 12',
    };

    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={paths[name] || paths.list} />
        </svg>
    );
};

function isNavItemActive(item, currentRoute) {
    if (item.routes?.length && currentRoute) {
        return item.routes.includes(currentRoute);
    }

    return false;
}

function NavLink({ item, onNavigate }) {
    const { currentRoute } = usePage().props;
    const active = isNavItemActive(item, currentRoute);

    return (
        <Link
            href={item.href}
            onClick={onNavigate}
            className={`flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors ${
                active
                    ? 'bg-sterling-gold text-sterling-green-darker shadow-sm'
                    : 'text-white/80 hover:bg-sterling-green-dark hover:text-white'
            }`}
        >
            {item.icon && <Icon name={item.icon} className="h-4 w-4 shrink-0" />}
            {item.name}
        </Link>
    );
}

function NavGroup({ item, onNavigate }) {
    const { currentRoute } = usePage().props;
    const isChildActive = item.children?.some((child) => isNavItemActive(child, currentRoute));
    const [open, setOpen] = useState(isChildActive);

    return (
        <div>
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                className={`flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-sm font-medium transition-colors ${
                    isChildActive
                        ? 'bg-sterling-green-dark text-sterling-gold'
                        : 'text-white/80 hover:bg-sterling-green-dark hover:text-white'
                }`}
            >
                <span className="flex items-center gap-3">
                    <Icon name={item.icon} className="h-4 w-4 shrink-0" />
                    {item.name}
                </span>
                <Icon name="chevron" className={`h-4 w-4 shrink-0 transition-transform ${open ? 'rotate-180' : ''}`} />
            </button>
            {open && (
                <div className="ml-4 mt-1 space-y-0.5 border-l border-sterling-green-light/40 pl-3">
                    {item.children.map((child) => (
                        <NavLink key={child.name} item={child} onNavigate={onNavigate} />
                    ))}
                </div>
            )}
        </div>
    );
}

function NavSection({ item, onNavigate }) {
    const { currentRoute } = usePage().props;
    const isChildActive = item.children?.some((child) => isNavItemActive(child, currentRoute));
    const [open, setOpen] = useState(true);

    return (
        <div className="mt-4">
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                className={`flex w-full items-center justify-between rounded-lg px-3 py-2 text-xs font-semibold uppercase tracking-widest transition-colors ${
                    isChildActive ? 'text-sterling-gold' : 'text-white/50 hover:text-white/70'
                }`}
            >
                <span className="flex items-center gap-2">
                    <Icon name={item.icon || 'credit-card'} className="h-3.5 w-3.5" />
                    {item.name}
                </span>
                <Icon name="chevron" className={`h-3 w-3 transition-transform ${open ? 'rotate-180' : ''}`} />
            </button>
            {open && (
                <div className="mt-1 space-y-0.5">
                    {item.children.map((child) => (
                        <NavLink key={child.name} item={child} onNavigate={onNavigate} />
                    ))}
                </div>
            )}
        </div>
    );
}

function SidebarContent({ onClose }) {
    const { navigation, auth } = usePage().props;

    const handleNavigate = () => {
        if (typeof window !== 'undefined' && window.matchMedia('(max-width: 1023px)').matches) {
            onClose();
        }
    };

    return (
        <div className="flex h-full flex-col overflow-y-auto bg-sterling-green text-white">
            <div className="flex shrink-0 items-center justify-between gap-2 border-b border-sterling-green-dark bg-white px-4 py-4">
                <SterlingLogo size="sm" className="min-w-0 object-left" />
                <button
                    type="button"
                    onClick={onClose}
                    className="rounded-lg p-2 text-sterling-green hover:bg-sterling-green-50"
                    aria-label="Close navigation"
                >
                    <Icon name="close" className="h-5 w-5" />
                </button>
            </div>

            <nav className="flex-1 space-y-1 px-3 py-4">
                {navigation?.map((item) => {
                    if (item.type === 'group') {
                        return <NavGroup key={item.name} item={item} onNavigate={handleNavigate} />;
                    }
                    if (item.type === 'section') {
                        return <NavSection key={item.name} item={item} onNavigate={handleNavigate} />;
                    }
                    return <NavLink key={item.name} item={item} onNavigate={handleNavigate} />;
                })}
            </nav>

            <div className="shrink-0 border-t border-sterling-green-dark p-4">
                <p className="text-sm font-medium text-white">{auth?.user?.name}</p>
                <p className="text-xs text-white/60">{auth?.user?.role?.name}</p>
            </div>
        </div>
    );
}

export default function Sidebar({ open, onClose }) {
    return (
        <aside
                id="app-sidebar"
                className={`fixed inset-y-0 left-0 z-50 w-64 shadow-xl transition-transform duration-300 ease-in-out ${
                    open ? 'translate-x-0' : '-translate-x-full'
                }`}
                aria-hidden={!open}
            >
                <SidebarContent onClose={onClose} />
        </aside>
    );
}
