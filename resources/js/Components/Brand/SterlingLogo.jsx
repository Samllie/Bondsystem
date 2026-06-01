const sizes = {
    sm: 'h-[clamp(2rem,5vw,2.25rem)] w-auto max-w-[min(100%,12.5rem)]',
    md: 'h-[clamp(2.5rem,6vw,2.75rem)] w-auto max-w-[min(100%,16.25rem)]',
    lg: 'h-[clamp(2.75rem,7vw,3rem)] w-auto max-w-[min(100%,20rem)]',
    xl: 'h-[clamp(3rem,8vw,4rem)] w-auto max-w-[min(100%,23.75rem)]',
};

export default function SterlingLogo({
    size = 'md',
    className = '',
    alt = 'Sterling Insurance Company, Inc.',
}) {
    return (
        <img
            src="/images/sterling-logo.png"
            alt={alt}
            className={`object-contain ${sizes[size] ?? sizes.md} ${className}`}
        />
    );
}
