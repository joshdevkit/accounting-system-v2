import type {
    PosSalesOptions,
    PosSalesDetails,
} from "@/components/pos/pos-entry-form";
import { Head } from "@inertiajs/react";
import {
    PosEntryForm,
    type PosAmountField,
} from "@/components/pos/pos-entry-form";
import type { PosPostingMappingRow } from "@/types/models";

type Props = {
    pos: {
        id: number;
        calculation_version: string | null;
        branch_id: number;
        business_date: string;
        pos_reference: string;
        notes: string | null;
        amounts: Record<string, number>;
        sales_details: PosSalesDetails;
    };
    branches: { id: number; name: string; code: string }[];
    mapping: PosPostingMappingRow[];
    mappingByBranch: Record<string, PosPostingMappingRow[]>;
    salesOptions: PosSalesOptions;
    currency: string;
};

export default function EditPosEntry({
    pos,
    branches,
    mapping,
    mappingByBranch,
    salesOptions,
    currency,
}: Props) {
    // The controller hands these back in pesos, not cents, so they only need
    // trimming down to the two decimals the form accepts.
    const amounts = Object.fromEntries(
        Object.entries(pos.amounts).map(([field, value]) => [
            field,
            value.toFixed(2),
        ]),
    ) as Partial<Record<PosAmountField, string>>;

    return (
        <>
            <Head title={`Edit ${pos.pos_reference}`} />

            <PosEntryForm
                action={`/pos-entries/${pos.id}`}
                method="put"
                branches={branches}
                mapping={mapping}
                mappingByBranch={mappingByBranch}
                salesOptions={salesOptions}
                currency={currency}
                backHref={`/pos-entries/${pos.id}`}
                heading={{
                    title: `Edit ${pos.pos_reference}`,
                    description: "Draft summaries can still be corrected",
                }}
                initial={{
                    calculation_version: pos.calculation_version,
                    branch_id: String(pos.branch_id),
                    business_date: pos.business_date,
                    pos_reference: pos.pos_reference,
                    notes: pos.notes ?? "",
                    ...amounts,
                    ...pos.sales_details,
                }}
            />
        </>
    );
}
