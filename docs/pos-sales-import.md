# POS sales entry import

## Updated accountant workbook: Test Import for POS

The actual-cost/increment headers select `sales_format_3`. Earlier files retain `sales_format_2` calculations documented below; existing records are not silently converted.

- Each invoice creates a POS draft **and a linked journal draft**, atomically with the entire import. An accounting period must cover the invoice date. Duplicate/invalid rows or a missing period roll back both records for the whole batch.
- Successful imports open a generated sales report with Date, Reference, Guest, Address, TIN, Business Source, VAT Inclusive, VATable, VAT Exempt, VAT, Discount, Withholding and Amount Due. References are naturally sorted; links open the POS invoice and its saved JE. The invoice screen links back to its batch report.
- Gross sales = I–M + N + O–AA: income including actual costs, increments, and complementary rooms, excluding payments. This matches the workbook's own `SUM(I:N)`-through-`AA` reconciliation, where income less payments equals the withholding. Sales report VATable = gross / 1.12 for VATable invoices, and the full gross for Non-VATable/VAT-Exempt invoices; report VAT = VATable × 12% when VATable. Withholding = VATable × selected rate. Discount and exempt rules remain as described below.
- The JE follows the new concrete formulas: gift vouchers debit unearned revenue; key-card and amenities actual costs debit COGS and credit inventory; SPA/AVL/other actual costs credit their supplier payables; increments credit their income accounts. Room revenue = I–N minus vouchers. JE withholding uses I–AA / 1.12; JE VAT uses (I–AA minus vouchers) / 1.12 × 12% when VATable. The withholding bases now agree with the report; the JE VAT base still excludes gift vouchers while the report VAT does not.
- Amenities increment uses **the current invoice**, not the sample's W18 batch-total reference. This avoids duplicating the whole batch's amenities income into every invoice JE.
- For 001236: gross 55,838.00, amount due 54,840.89; workbook JE debit 58,638.00, credit 58,499.21, variance 138.78. A persisted JSON calculation snapshot retains unrounded Excel-style arithmetic. Actual journal lines remain centavo-rounded; their variance is 138.79. Both are visible, labelled, and never forced to balance.
- New manual entries use this format. Editing regenerates the same linked draft; deleting the POS draft removes its journal. Generated journals cannot be edited independently or posted through the journal endpoint.
- Missing seeded account mappings remain explicit **unmapped draft lines**, not invented account codes or omitted amounts. Only matching active, leaf accounts within the company are assigned. Workbook journals are review-only until accounting mappings/rules are approved. No ledger entries are posted.

Apply `2026_10_17_000002_add_pos_import_reports.php` along with the preceding POS migrations before importing. There is no automatic re-import/backfill of older invoices. Regression coverage: `tests/Feature/PosWorkbookDraftTest.php`.

## Sales Format_Final — Maxi (Final)

If both Maxi sheets are present, import prefers `Maxi (Final)` for Maxi; exact `Gusto (Final)` is similarly preferred for Gusto. The finalized layout has separate actual-cost and increment columns, including laundry, and is tagged `sales_format_4`.

- Report gross sales = the final sheet's G:Z total: rooms, complementary rooms, actual costs and increments; tender/payment columns are excluded. For VATable sales, VATable amount = gross ÷ 1.12, report VAT = VATable × 12%, withholding = VATable × selected rate, and amount due adds VAT and subtracts discount/withholding. For the workbook's VAT Exempt Sales classification, gross ÷ 1.12 goes in VAT Exempt Sales and is used for amount due; VATable Sales is zero.
- JE gift vouchers debit Unearned Revenue at amount ÷ 1.12. JE withholding uses gross ÷ 1.12 × rate. JE output VAT subtracts the voucher's VAT from gross VAT. Room revenue credits room columns ÷ 1.12.
- Actual-cost/increment items credit the supplier or inventory for actual cost and credit Other Income for increment less VAT on that item's full gross. Items without a separate cost column credit gross less their VAT. These formulas reproduce Maxi (Final)'s SPA, laundry, shuttle, smoking, key-card, amenities, AVL and other-income rows.
- The reference invoice 001236 produces gross 55,838.00 and amount due 54,840.89. JE debits and credits each display 55,516.57, with the unrounded Excel variance 0.002857 pesos (displays 0.00). The earlier Maxi sheet gives a different JE, so the `Final` sheet is selected when both are available.

Apply migration `2026_10_17_000003_add_pos_laundry_increment.php` for the additional laundry increment field.

## Earlier Sales Format workbooks

The Sales Format import creates one draft POS entry per source reference. It preserves reference text (including leading zeroes), guest name, address, TIN, business source, arrival/departure times, all thirteen income categories, split payments, VAT classification, withholding tax rate, and discount type.

Choose the matching branch. In a multi-sheet workbook the importer reads only the worksheet matching the branch code, case-insensitively (Maxi or Gusto). A workbook containing a single sales worksheet is also accepted. The supplied Gusto sheet is empty; it needs sales headers and rows before it can be imported.

The importer reads the two-row sales header, stops at the generated-report section, and ignores totals. Lists and illustration sheets are not imported. Invalid amounts, invalid selections, and duplicate branch/date/reference combinations reject the complete batch with the source row number.

The revised format supports the annotated Basic Room Rates header, column N complementary rooms, the combined bank transfer / QR Ph / online booking column, and Corporate & Government discounts. Legacy header names still work.

Create, edit, and import use the same server calculation. The entry page shows the calculated sales invoice and a journal preview with amounts replacing the workbook's xx placeholders. Existing detailed drafts also show a recalculated preview without changing their stored record until saved. Detailed entries remain drafts; the preview does not write journal lines to the ledger. Existing terminal summaries retain their posting flow.

The formulas intentionally follow the workbook's written ranges and sides:

- Gross sales = income I–M + O–V plus payments W–AA, as specified by I–M + O–AA. Complementary rooms in N are excluded. Separate legacy online booking and QR Ph amounts are included with payments.
- VATable sales = gross sales / 1.12 when the invoice is VATable; for Non-VATable and VAT-Exempt invoices the whole gross is the VATable-sales base, so a non-VATable amount is not silently reduced by the VAT factor.
- Senior/PWD VAT-exempt sales = room charges I–M / 1.12. This is added separately rather than removed from VATable sales.
- VAT = VATable sales × 12% only when VATable is selected.
- Senior/PWD discounts use 20% of exempt room charges. Employee discounts use 25%. Promo, special-open, and corporate/government discounts use the entered editable percentage.
- Percentage bases not explicitly named in the workbook are implemented as VATable sales for other discounts and withholding. These bases are shown in Calculation details for review.
- Amount due = VATable + VAT-exempt + VAT − discount − withholding. Values are rounded to cents at each displayed step.

Blank selections remain blank. An unselected discount or withholding rate produces zero, and blank VAT classification does not trigger VAT. Selecting an editable discount without a rate displays “Rate required”, rather than presenting its zero-rate provisional result as final.

The JE preview retains the workbook's credit-side discounts and gift vouchers, debit-side complementary rooms, separate income accounts, and gross room revenue. It shows the debit/credit difference without creating an over/short adjustment. Supplier payables use AVL gross less each entered increment, matching the wording on all three supplier rows. Missing increments produce “Input required” and known-amount subtotals.

For the revised sample reference 001234, income and payments are each 6,188.00, workbook gross is 12,376.00, VATable sales 11,050.00, exempt sales 5,525.00, VAT 1,326.00, discount 1,105.00, and amount due 16,796.00. JE debits are 6,188.00 and credits 8,619.00.

POS account mappings resolve by branch suffix: Maxi 01 and Gusto 02. Codes follow the chart-of-accounts and income-statement seeders. Card settlement, other payments, local tax, and some branch-specific charges remain unmapped where there is no confirmed seeded account. Missing mappings block posting rather than silently dropping amounts. The Gusto default revenue/receivable mappings refer to FnB; category-specific Gusto posting remains to be defined.

Run the new migration before using the form or import:

```sh
php artisan migrate
```

Regression coverage is in `tests/Feature/PosSalesFormatTest.php` and `tests/Unit/PosSalesCalculationTest.php`. The default SQLite suite currently stops at an older MySQL-only journal source-type migration; this is independent of the POS schema migrations.
