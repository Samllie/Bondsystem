import { useLogout } from '@/Contexts/PageTransitionContext';

export default function LogoutButton({ className = '', children = 'Log Out', ...props }) {
    const logout = useLogout();

    return (
        <button type="button" className={className} onClick={logout} {...props}>
            {children}
        </button>
    );
}
