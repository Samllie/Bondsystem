function getInitials(name) {
    if (!name?.trim()) {
        return '?';
    }

    const parts = name.trim().split(/\s+/);

    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase();
}

export default function UserAvatar({ name, className = '' }) {
    return (
        <span
            className={`group inline-flex h-9 max-w-9 cursor-pointer items-center overflow-hidden rounded-full bg-sterling-green shadow-sm ring-2 ring-white transition-[max-width,background-color,box-shadow,ring-color] duration-300 ease-out hover:max-w-[14rem] hover:bg-sterling-green-dark hover:shadow-md hover:ring-sterling-gold/50 ${className}`}
        >
            <span className="flex h-9 w-9 shrink-0 items-center justify-center text-sm font-semibold tracking-wide text-white">
                {getInitials(name)}
            </span>
            {name ? (
                <span className="flex min-w-0 items-center gap-1.5 whitespace-nowrap pr-3 opacity-0 transition-opacity duration-200 group-hover:opacity-100">
                    <span className="truncate text-sm font-medium text-white">{name}</span>
                    <svg
                        className="h-4 w-4 shrink-0 text-white/80"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        aria-hidden
                    >
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                    </svg>
                </span>
            ) : null}
        </span>
    );
}
