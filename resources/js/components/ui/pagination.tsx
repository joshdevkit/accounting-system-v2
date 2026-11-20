import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';

export type PaginationLink = {
    label: string;
    url: string | null;
    active: boolean;
};

/**
 * Renders the `links` Laravel ships with a LengthAwarePaginator, normalised
 * server side into `{ label, url, active }[]`.
 */
export function Pagination({
    links,
    className,
}: {
    links: PaginationLink[];
    className?: string;
}) {
    const usable = links.filter((link) => link.url);

    if (usable.length <= 1) return null;

    return (
        <nav
            aria-label="Pagination"
            className={cn(
                'flex flex-wrap items-center gap-1 px-4 py-3 text-sm',
                className,
            )}
        >
            {links.map((link, index) => {
                const isPrev = link.label.includes('&laquo;') || link.label === 'Previous';
                const isNext = link.label.includes('&raquo;') || link.label === 'Next';
                const disabled = !link.url || link.active;

                const content = (
                    <span
                        className={cn(
                            'flex h-8 min-w-8 items-center justify-center rounded-md px-2 transition-colors',
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                            disabled && !link.active && 'pointer-events-none opacity-50',
                        )}
                    >
                        {isPrev && <ChevronLeft className="size-4" />}
                        {isNext && <ChevronRight className="size-4" />}
                        {!isPrev && !isNext && link.label}
                    </span>
                );

                return link.url && !link.active ? (
                    <Link
                        key={`${link.label}-${index}`}
                        href={link.url}
                        preserveScroll
                        prefetch={false}
                        aria-current={undefined}
                    >
                        {content}
                    </Link>
                ) : (
                    <span key={`${link.label}-${index}`} aria-current={link.active ? 'page' : undefined}>
                        {content}
                    </span>
                );
            })}
        </nav>
    );
}
