/**
 * Full-page file download link that bypasses Inertia client-side navigation.
 */
export default function FileDownloadLink({ href, className = 'btn-secondary', children, ...props }) {
    return (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer"
            className={className}
            {...props}
        >
            {children}
        </a>
    );
}
