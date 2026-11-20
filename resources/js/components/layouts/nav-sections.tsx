// import {
//     Bot,
//     BookText,
//     Building2,
//     CalendarRange,
//     LayoutGrid,
//     ListTree,
//     Receipt,
//     ReceiptText,
//     Rows3,
//     Scale,
//     Scale3d,
//     ScrollText,
//     Settings,
//     ShoppingBag,
//     ShoppingCart,
//     TrendingUp,
//     Contact2,
//     Users,
//     ShieldCheck,
//     KeyRound,
// } from "lucide-react";

// export type NavItem = {
//     label: string;
//     href: string;
//     icon: React.ComponentType<{ className?: string }>;
//     /** Permission required to see this item. Omit for always-visible items. */
//     permission?: string;
// };

// export type NavSection = {
//     label: string;
//     items: NavItem[];
// };

// export const navSections: NavSection[] = [
//     {
//         label: "Overview",
//         items: [{ label: "Dashboard", href: "/dashboard", icon: LayoutGrid }],
//     },
//     {
//         label: "Accounting",
//         items: [
//             { label: "Chart of accounts", href: "/accounts", icon: ListTree, permission: "manage chart of accounts" },
//             { label: "Journal entries", href: "/journal-entries", icon: BookText, permission: "create journal entries" },
//             { label: "Fiscal years", href: "/fiscal-years", icon: CalendarRange, permission: "manage fiscal years" },
//         ],
//     },
//     {
//         label: "Operations",
//         items: [
//             { label: "Branches", href: "/branches", icon: Building2 },
//             { label: "POS entries", href: "/pos-entries", icon: ReceiptText, permission: "record pos entries" },
//             { label: "Expenses", href: "/expenses", icon: Receipt, permission: "record expenses" },
//             { label: "Purchases", href: "/purchases", icon: ShoppingCart, permission: "record purchases" },
//             { label: "Sales", href: "/sales", icon: ShoppingBag, permission: "record sales" },
//             { label: "Contacts", href: "/contacts", icon: Contact2, permission: "manage contacts" },
//         ],
//     },
//     {
//         label: "Reports",
//         items: [
//             { label: "Trial balance", href: "/reports/trial-balance", icon: Scale, permission: "view reports" },
//             { label: "General ledger", href: "/reports/ledger", icon: Rows3, permission: "view reports" },
//             { label: "Income statement", href: "/reports/income-statement", icon: TrendingUp, permission: "view reports" },
//             { label: "Balance sheet", href: "/reports/balance-sheet", icon: Scale3d, permission: "view reports" },
//         ],
//     },
//     {
//         label: "System",
//         items: [
//             { label: "Company settings", href: "/settings/company", icon: Settings, permission: "manage company settings" },
//             { label: "Audit log", href: "/audit-log", icon: ScrollText, permission: "manage users" },
//             { label: "Roles", href: "/roles", icon: ShieldCheck, permission: "manage users" },
//             { label: 'Permissions', href: '/permissions', icon: KeyRound, permission: 'manage users' },
//             { label: "Users", href: "/users", icon: Users, permission: "manage users" },
//         ],
//     },
// ];

import banking from "@/routes/banking";
import {
    BookText,
    Building2,
    CalendarRange,
    LayoutGrid,
    ListTree,
    Receipt,
    ReceiptText,
    Rows3,
    Scale,
    Scale3d,
    ScrollText,
    Settings,
    ShoppingCart,
    TrendingUp,
    Contact2,
    Users,
    ShieldCheck,
    KeyRound,
    UserRound,
    ClipboardList,
    NotebookText,
    Landmark,
    ArrowLeftRight,
    Monitor,
} from "lucide-react";

export type NavItem = {
    label: string;
    href: string;
    icon: React.ComponentType<{ className?: string }>;
    /** Permission required to see this item. Omit for always-visible items. */
    permission?: string;
    /** Not wired to a route yet — renders as non-clickable with a "Soon" badge. */
    comingSoon?: boolean;
    /**
     * Extra paths that should also mark this item active, without changing
     * where the link itself points — e.g. a drill-down report reached from
     * this page. Query strings on the current path are ignored when matching.
     */
    activeMatch?: string[];
};

export type NavSection = {
    label: string;
    items: NavItem[];
};

export const navSections: NavSection[] = [
    {
        label: "Overview",
        items: [{ label: "Dashboard", href: "/dashboard", icon: LayoutGrid }],
    },
    {
        label: "Financials",
        items: [
            {
                label: "Journal entries",
                href: "/journal-entries",
                icon: BookText,
                permission: "create journal entries",
            },
            {
                label: "JE - Expenses (General & Administrative)",
                href: "/expenses",
                icon: Receipt,
                permission: "record expenses",
            },
            {
                label: "JE - Purchases (Operations)",
                href: "/purchases",
                icon: ShoppingCart,
                permission: "record purchases",
            },
            {
                label: "POS entries",
                href: "/pos-entries",
                icon: ReceiptText,
                permission: "record pos entries",
            },
            {
                label: "Chart of accounts",
                href: "/accounts",
                icon: ListTree,
                permission: "manage chart of accounts",
                activeMatch: ["/reports/ledger"],
            },
        ],
    },
    {
        label: "Purchases - A/P",
        items: [
            {
                label: "Vendor Masterdata",
                href: "/contacts?type=vendor",
                icon: Contact2,
                permission: "manage contacts",
            },
            {
                label: "Vendor Ledger",
                href: "/vendor-ledger",
                icon: ClipboardList,
            },
        ],
    },
    {
        label: "Sales - A/R",
        items: [
            {
                label: "Guest Masterdata",
                href: "/contacts?type=customer",
                icon: UserRound,
                permission: "manage contacts",
            },
            {
                label: "City Ledger",
                href: "/city-ledger",
                icon: NotebookText,
            },
        ],
    },
    {
        label: "Banking",
        items: [
            {
                label: "Outgoing Payments",
                href: banking.outgoing().url,
                icon: Landmark,
                comingSoon: true,
            },
        ],
    },
    {
        label: "Reports",
        items: [
            {
                label: "Trial balance",
                href: "/reports/trial-balance",
                icon: Scale,
                permission: "view reports",
            },
            {
                label: "Profit and Loss",
                href: "/reports/income-statement",
                icon: TrendingUp,
                permission: "view reports",
            },
            {
                label: "Cash Flow",
                href: "#",
                icon: ArrowLeftRight,
                comingSoon: true,
            },
            {
                label: "Financial Position",
                href: "/reports/balance-sheet",
                icon: Scale3d,
                permission: "view reports",
            },
        ],
    },
    {
        label: "System",
        items: [
            { label: "Branches", href: "/branches", icon: Building2 },
            {
                label: "Fiscal years",
                href: "/fiscal-years",
                icon: CalendarRange,
                permission: "manage fiscal years",
            },
            {
                label: "Company settings",
                href: "/settings/company",
                icon: Settings,
                permission: "manage company settings",
            },
            {
                label: "Audit log",
                href: "/audit-log",
                icon: ScrollText,
                permission: "manage users",
            },
            {
                label: "Roles",
                href: "/roles",
                icon: ShieldCheck,
                permission: "manage users",
            },
            {
                label: "Permissions",
                href: "/permissions",
                icon: KeyRound,
                permission: "manage users",
            },
            {
                label: "Users",
                href: "/users",
                icon: Users,
                permission: "manage users",
            },
        ],
    },
];
