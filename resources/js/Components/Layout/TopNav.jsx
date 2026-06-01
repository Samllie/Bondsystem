import LogoutButton from '@/Components/Auth/LogoutButton';
import Dropdown from '@/Components/Dropdown';

export default function TopNav({ onMenuClick, sidebarOpen }) {
    return (
        <header className="sticky top-0 z-30 border-b border-sterling-green/10 bg-white/95 shadow-sm backdrop-blur-sm">
            <div className="flex h-16 items-center justify-between gap-3 px-4 sm:px-6">
                <button
                    type="button"
                    onClick={onMenuClick}
                    className="inline-flex items-center gap-2 rounded-lg border border-sterling-green/15 bg-white px-3 py-2 text-sm font-medium text-sterling-green shadow-sm transition hover:bg-sterling-green-50"
                    aria-expanded={sidebarOpen}
                    aria-controls="app-sidebar"
                >
                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden>
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M4 6h16M4 12h16M4 18h16"
                        />
                    </svg>
                    <span className="hidden sm:inline">{sidebarOpen ? 'Hide menu' : 'Show menu'}</span>
                </button>

                <div className="flex-1" />

                <Dropdown>
                    <Dropdown.Trigger>
                        <span className="inline-flex cursor-pointer rounded-lg px-3 py-2 text-sm font-medium text-sterling-green hover:bg-sterling-green-50">
                            Account
                        </span>
                    </Dropdown.Trigger>
                    <Dropdown.Content>
                        <Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link>
                        <LogoutButton className="block w-full px-4 py-2 text-start text-sm leading-5 text-sterling-green transition duration-150 ease-in-out hover:bg-sterling-green-50 focus:bg-sterling-green-50 focus:outline-none">
                            Log Out
                        </LogoutButton>
                    </Dropdown.Content>
                </Dropdown>
            </div>
        </header>
    );
}
