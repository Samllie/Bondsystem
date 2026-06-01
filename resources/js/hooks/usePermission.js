import { usePage } from '@inertiajs/react';

export function usePermission() {
    const { auth } = usePage().props;

    const can = (permission) => {
        if (!auth?.user?.permissions) {
            return false;
        }

        return auth.user.permissions.includes(permission);
    };

    const hasRole = (slug) => auth?.user?.role?.slug === slug;

    return { can, hasRole, user: auth?.user };
}
