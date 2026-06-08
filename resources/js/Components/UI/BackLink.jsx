import { Link } from '@inertiajs/react';

export default function BackLink({ href, children, className = '' }) {
    return (
        <Link
            href={href}
            className={`mb-4 inline-flex items-center text-sm font-medium text-sterling-green transition hover:text-sterling-green-dark hover:underline ${className}`}
        >
            ← {children}
        </Link>
    );
}
