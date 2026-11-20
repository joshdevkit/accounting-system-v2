import type { PosSalesOptions } from "@/components/pos/pos-entry-form";
import { Head } from "@inertiajs/react";
import { PosEntryForm } from "@/components/pos/pos-entry-form";
import type { PosPostingMappingRow } from "@/types/models";

type Props = {
    branches: { id: number; name: string; code: string }[];
    mapping: PosPostingMappingRow[];
    mappingByBranch: Record<string, PosPostingMappingRow[]>;
    salesOptions: PosSalesOptions;
    defaults: {
        branch_id: number | null;
        business_date: string;
        pos_reference: string;
    };
    currency: string;
};

export default function CreatePosEntry({
    branches,
    mapping,
    mappingByBranch,
    salesOptions,
    defaults,
    currency,
}: Props) {
    return (
        <>
            <Head title="Record POS entry" />

            <PosEntryForm
                action="/pos-entries"
                method="post"
                branches={branches}
                mapping={mapping}
                mappingByBranch={mappingByBranch}
                salesOptions={salesOptions}
                currency={currency}
                backHref="/pos-entries"
                heading={{
                    title: "Record POS entry",
                    description:
                        "Record sales details, income and split payments",
                }}
                initial={{
                    branch_id: defaults.branch_id
                        ? String(defaults.branch_id)
                        : "",
                    business_date: defaults.business_date,
                    pos_reference: defaults.pos_reference,
                }}
            />
        </>
    );
}
