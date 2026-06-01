export default function TableSearchInput({
    inputRef,
    defaultSearch = '',
    onInput,
    isSearching = false,
    placeholder,
    className = '',
    wrapperClassName = 'relative w-full max-w-md',
}) {
    return (
        <div className={wrapperClassName}>
            <input
                ref={inputRef}
                type="search"
                defaultValue={defaultSearch}
                onInput={onInput}
                placeholder={placeholder}
                className={`rounded-lg border border-slate-300 shadow-sm focus:border-sterling-gold focus:ring-sterling-gold ${className} ${isSearching ? 'pr-24' : ''}`}
            />
            {isSearching && (
                <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-medium text-sterling-gold">
                    Searching…
                </span>
            )}
        </div>
    );
}
