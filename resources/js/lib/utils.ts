import type { ClassValue } from 'clsx';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/**
 * Query strings for `router.get`: empty selects and null filters would
 * otherwise end up in the URL and in the paginator links.
 */
export function compactQuery(
    values: Record<string, unknown>,
): Record<string, string | number> {
    const query: Record<string, string | number> = {};

    for (const [key, value] of Object.entries(values)) {
        if (value === null || value === undefined || value === '') continue;

        query[key] =
            typeof value === 'boolean'
                ? value
                    ? '1'
                    : '0'
                : (value as string | number);
    }

    return query;
}
