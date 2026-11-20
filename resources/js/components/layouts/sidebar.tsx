import { Link, usePage } from "@inertiajs/react";
import { ChevronDown, LogOut, X } from "lucide-react";
import { useEffect, useState } from "react";
import { cn } from "@/lib/utils";
import type { Auth } from "@/types";
import { navSections, type NavItem } from "./nav-sections";
import { logout } from "@/routes";
import { ModeToggle } from "../theme/mode-toggle";

function initials(name: string) {
    return name
        .split(" ")
        .map((part) => part[0])
        .filter(Boolean)
        .slice(0, 2)
        .join("")
        .toUpperCase();
}

function parseUrl(url: string) {
    const [pathAndQuery] = url.split("#");
    const [path, query = ""] = pathAndQuery.split("?");

    return { path, params: new URLSearchParams(query) };
}

function matchesHref(
    current: ReturnType<typeof parseUrl>,
    href: string,
): boolean {
    const target = parseUrl(href);

    const pathMatches =
        target.path === "/dashboard"
            ? current.path === target.path
            : current.path === target.path ||
              current.path.startsWith(`${target.path}/`);

    if (!pathMatches) return false;

    // Every query param on the nav href must match the current URL exactly.
    for (const [key, value] of target.params) {
        if (current.params.get(key) !== value) return false;
    }

    return true;
}

function isItemActive(currentUrl: string, item: NavItem): boolean {
    if (item.href === "#") return false;

    const current = parseUrl(currentUrl);

    return (
        matchesHref(current, item.href) ||
        (item.activeMatch ?? []).some((extra) => matchesHref(current, extra))
    );
}

export default function Sidebar({
    open,
    onClose,
    auth,
}: {
    open: boolean;
    onClose: () => void;
    currentPath: string;
    auth?: Auth;
}) {
    const { props,  url } = usePage<{ auth: { permissions: string[] } }>();
    const permissions = new Set(props.auth?.permissions ?? []);
    const currentPath = url;
    const visibleSections = navSections
        .map((section) => ({
            ...section,
            items: section.items.filter(
                (item) => !item.permission || permissions.has(item.permission),
            ),
        }))
        .filter((section) => section.items.length > 0);

    // Keep the initial render deterministic for SSR/hydration.
    const [expanded, setExpanded] = useState<Record<string, boolean>>({});

    // Open the section containing the current page after hydration.
    useEffect(() => {
        const next: Record<string, boolean> = {};

        for (const section of visibleSections) {
            if (section.items.some((item) => isItemActive(currentPath, item))) {
                next[section.label] = true;
            }
        }

        setExpanded(next);
    }, [currentPath]);

    const toggleSection = (label: string) => {
        setExpanded((current) => ({
            ...current,
            [label]: !current[label],
        }));
    };

    return (
        <>
            {open && (
                <div
                    className="fixed inset-0 z-40 bg-black/40 lg:hidden"
                    onClick={onClose}
                    aria-hidden="true"
                />
            )}

            <aside
                className={cn(
                    "fixed inset-y-0 left-0 z-50 flex w-64 shrink-0 flex-col border-r bg-sidebar text-sidebar-foreground transition-transform duration-200 ease-in-out lg:static lg:z-auto",
                    open ? "translate-x-0" : "-translate-x-full lg:hidden",
                )}
            >
                <div className="flex h-14 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border px-5">
                    <div className="flex items-center gap-2">
                        <div className="flex size-7 items-center justify-center rounded-md bg-sidebar-primary text-sm font-semibold text-sidebar-primary-foreground">
                            M
                        </div>

                        <div className="leading-tight">
                            <p className="text-sm font-semibold">
                                {import.meta.env.VITE_APP_NAME}
                            </p>

                            <p className="text-xs text-sidebar-foreground/60">
                                Accounting
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        onClick={onClose}
                        className="flex size-7 items-center justify-center rounded-md text-sidebar-foreground/60 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground lg:hidden"
                        aria-label="Close sidebar"
                    >
                        <X className="size-4" />
                    </button>
                </div>

                <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-5">
                    {visibleSections.map((section) => {
                        const isOpen = expanded[section.label] ?? false;

                        const hasActiveItem = section.items.some((item) =>
                            isItemActive(currentPath, item),
                        );

                        return (
                            <div key={section.label}>
                                <button
                                    type="button"
                                    onClick={() => toggleSection(section.label)}
                                    className={cn(
                                        "flex w-full items-center justify-between rounded-md px-2.5 py-1.5 text-sm font-medium transition-colors",
                                        hasActiveItem
                                            ? "text-sidebar-accent-foreground"
                                            : "text-sidebar-foreground/80 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground",
                                    )}
                                >
                                    <span>{section.label}</span>

                                    <ChevronDown
                                        className={cn(
                                            "text-sidebar-foreground/50 size-4 shrink-0 transition-transform duration-150",
                                            isOpen ? "rotate-0" : "-rotate-90",
                                        )}
                                    />
                                </button>

                                {isOpen && (
                                    <div className="border-sidebar-border mt-0.5 ml-3.5 space-y-0.5 border-l pl-2.5">
                                        {section.items.map((item) => {
                                            const Icon = item.icon;

                                            const active = isItemActive(
                                                currentPath,
                                                item,
                                            );

                                            return (
                                                <Link
                                                    key={item.href}
                                                    href={item.href}
                                                    className={cn(
                                                        "flex items-center gap-2.5 rounded-md px-2.5 py-1.5 text-sm transition-colors",
                                                        active
                                                            ? "bg-sidebar-accent text-sidebar-accent-foreground"
                                                            : "text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground",
                                                    )}
                                                >
                                                    <Icon className="size-4 shrink-0" />
                                                    {item.label}
                                                </Link>
                                            );
                                        })}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </nav>

                <div className="shrink-0 border-t border-sidebar-border">
                    <div className="px-3 py-2">
                        <ModeToggle />
                    </div>

                    <div className="flex items-center gap-1 border-t border-sidebar-border px-2 py-2">
                        <Link
                            href="/settings/profile"
                            className="hover:bg-sidebar-accent flex flex-1 items-center gap-2.5 rounded-md px-2 py-1.5 transition-colors"
                        >
                            <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-sidebar-accent text-xs font-medium">
                                {auth?.user ? initials(auth.user.name) : "—"}
                            </div>

                            <div className="min-w-0 flex-1 leading-tight">
                                <p className="truncate text-sm font-medium">
                                    {auth?.user?.name ?? "Signed in user"}
                                </p>

                                <p className="truncate text-xs text-sidebar-foreground/60">
                                    {auth?.user?.email}
                                </p>
                            </div>
                        </Link>

                        <Link
                            href={logout()}
                            method="post"
                            as="button"
                            className="flex size-7 shrink-0 items-center justify-center rounded-md text-sidebar-foreground/60 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                            aria-label="Log out"
                        >
                            <LogOut className="size-4" />
                        </Link>
                    </div>
                </div>
            </aside>
        </>
    );
}
