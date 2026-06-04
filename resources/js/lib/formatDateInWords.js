function daySuffix(day) {
    const remainderTen = day % 10;
    const remainderHundred = day % 100;

    if (remainderTen === 1 && remainderHundred !== 11) {
        return 'st';
    }

    if (remainderTen === 2 && remainderHundred !== 12) {
        return 'nd';
    }

    if (remainderTen === 3 && remainderHundred !== 13) {
        return 'rd';
    }

    return 'th';
}

export function formatDateInWords(value) {
    if (!value) {
        return '';
    }

    const date = new Date(`${String(value).substring(0, 10)}T00:00:00`);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const day = date.getDate();
    const month = date.toLocaleString('en-US', { month: 'long' });
    const year = date.getFullYear();

    return `${day}${daySuffix(day)} day of ${month}, ${year}`;
}
