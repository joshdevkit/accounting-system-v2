// General & Administrative expenses use the same item entry format as purchases.
export {
    emptyPurchaseLine as emptyExpenseLine,
    purchaseLinesTotals as expenseLinesTotals,
    PurchaseLinesEditor as ExpenseLinesEditor,
} from "@/components/purchases/purchase-lines-editor";
export type { PurchaseLineInput as ExpenseLineInput } from "@/components/purchases/purchase-lines-editor";
