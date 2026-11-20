import { useRef, useState, type DragEvent } from "react";
import { router } from "@inertiajs/react";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import {
    Combobox,
    ComboboxContent,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from "@/components/ui/combobox";
import { FileSpreadsheet, Upload, X } from "lucide-react";
import * as SalesRoute from "@/routes/sales";

type Branch = { id: number; name: string; code: string };

type PreviewData = {
    fileName: string;
    headers: (string | null)[];
    rows: (string | null)[][];
    totalRows: number;
    truncated: boolean;
};

type SalesImportPreviewDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    branches: Branch[];
    preview?: PreviewData;
};

const ACCEPTED_EXTENSIONS = [".xlsx", ".xls"];

export default function SalesImportPreviewDialog({
    open,
    onOpenChange,
    branches,
    preview,
}: SalesImportPreviewDialogProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);

    const [branchId, setBranchId] = useState<number | "">("");
    const [file, setFile] = useState<File | null>(null);
    const [dragActive, setDragActive] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const selectedBranch = (branches ?? []).find((b) => b.id === branchId) ?? null;
    const safeBranches = branches ?? [];

    const reset = () => {
        setBranchId("");
        setFile(null);
        setError(null);
    };

    const close = () => {
        reset();
        onOpenChange(false);

        // Navigate back to /sales after a preview POST
        if (preview) {
            router.get(SalesRoute.index(), {}, { preserveScroll: true });
        }
    };

    const validateAndSetFile = (candidate: File) => {
        const isAccepted = ACCEPTED_EXTENSIONS.some((ext) =>
            candidate.name.toLowerCase().endsWith(ext),
        );

        if (!isAccepted) {
            setError("Please choose an .xlsx or .xls file.");
            return;
        }

        setError(null);
        setFile(candidate);
    };

    const onDrop = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        setDragActive(false);

        const dropped = event.dataTransfer.files?.[0];
        if (dropped) validateAndSetFile(dropped);
    };

    const submit = () => {
        if (!file || !branchId) return;

        setError(null);

        router.post(
            SalesRoute.preview(),
            { branch_id: branchId, file },
            {
                forceFormData: true,
                onError: (errors) => {
                    setError(
                        errors.file ??
                            errors.branch_id ??
                            "Preview failed. Check the file and try again.",
                    );
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={close}>
            <DialogContent
                className="
                    !fixed !inset-0 !top-0 !left-0 !translate-x-0 !translate-y-0
                    !h-screen !w-screen !max-w-none !rounded-none !border-0 !p-0
                    flex flex-col gap-0
                "
            >
                <DialogHeader className="shrink-0 border-b px-6 py-4">
                    <DialogTitle>Import Sales Data</DialogTitle>
                    <DialogDescription>
                        Upload an Excel file containing your sales data. The preview
                        shows the file contents verbatim. Import functionality will
                        be added after confirming the mapping with your accountant.
                    </DialogDescription>
                </DialogHeader>

                <div className="min-h-0 flex-1 overflow-auto">
                    <div className="mx-auto flex min-h-full w-full max-w-4xl flex-col p-6">
                        <div className="w-full space-y-4">
                            {!preview ? (
                                <>
                                    <Field label="Branch" htmlFor="sales_import_branch">
                                        <Combobox
                                            items={safeBranches}
                                            value={selectedBranch}
                                            onValueChange={(branch) =>
                                                setBranchId(branch ? branch.id : "")
                                            }
                                            itemToStringLabel={(branch) =>
                                                branch
                                                    ? `${branch.code} · ${branch.name}`
                                                    : ""
                                            }
                                        >
                                            <ComboboxInput
                                                id="sales_import_branch"
                                                placeholder="Choose a branch…"
                                                disabled={false}
                                                className="w-full"
                                            />
                                            <ComboboxContent>
                                                <ComboboxList>
                                                    {safeBranches.map((branch) => (
                                                        <ComboboxItem
                                                            key={branch.id}
                                                            value={branch}
                                                        >
                                                            {branch.code} ·{" "}
                                                            {branch.name}
                                                        </ComboboxItem>
                                                    ))}
                                                </ComboboxList>
                                            </ComboboxContent>
                                        </Combobox>
                                    </Field>

                                    <div
                                        onDragOver={(event) => {
                                            event.preventDefault();
                                            if (!file) setDragActive(true);
                                        }}
                                        onDragLeave={() => setDragActive(false)}
                                        onDrop={file ? undefined : onDrop}
                                        className={[
                                            "rounded-xl border border-dashed p-10 text-center transition-colors",
                                            dragActive ? "border-primary bg-primary/5" : "",
                                        ].join(" ")}
                                    >
                                        <div className="bg-muted mx-auto flex size-14 items-center justify-center rounded-xl">
                                            <FileSpreadsheet className="text-muted-foreground size-7" />
                                        </div>

                                        <h3 className="mt-4 text-base font-semibold">
                                            {file ? file.name : "Import Sales Excel Data"}
                                        </h3>

                                        <p className="text-muted-foreground mx-auto mt-1 max-w-md text-sm">
                                            {file
                                                ? `${(file.size / 1024).toFixed(0)} KB`
                                                : "Drag a file here, or choose one below."}
                                        </p>

                                        <div className="mt-6 flex items-center justify-center gap-2">
                                            <input
                                                ref={fileInputRef}
                                                type="file"
                                                accept={ACCEPTED_EXTENSIONS.join(",")}
                                                className="hidden"
                                                disabled={false}
                                                onChange={(event) => {
                                                    const picked = event.target.files?.[0];
                                                    if (picked) validateAndSetFile(picked);
                                                    event.target.value = "";
                                                }}
                                            />

                                            <Button
                                                type="button"
                                                variant={file ? "outline" : "default"}
                                                disabled={false}
                                                onClick={() => fileInputRef.current?.click()}
                                            >
                                                <Upload className="size-4" />
                                                {file ? "Choose a different file" : "Choose Excel File"}
                                            </Button>

                                            {file && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label="Remove file"
                                                    onClick={() => setFile(null)}
                                                >
                                                    <X className="size-4" />
                                                </Button>
                                            )}
                                        </div>

                                        <p className="text-muted-foreground mt-3 text-xs">
                                            Supported formats: .xlsx, .xls
                                        </p>
                                    </div>

                                    {error && (
                                        <p className="text-destructive text-sm">{error}</p>
                                    )}
                                </>
                            ) : (
                                <div className="space-y-2">
                                    <div className="flex items-center justify-between">
                                        <h3 className="text-sm font-semibold">
                                            Preview: {preview.fileName}
                                        </h3>
                                        <span className="text-xs text-muted-foreground">
                                            {preview.totalRows} rows{preview.truncated ? " (truncated at 200)" : ""}
                                        </span>
                                    </div>
                                    <div className="overflow-x-auto border rounded-lg">
                                        <table className="w-full text-xs">
                                            <thead>
                                                <tr className="bg-muted/50">
                                                    {preview.headers.map((header, i) => (
                                                        <th
                                                            key={i}
                                                            className="px-3 py-2 text-left font-medium whitespace-nowrap border-r last:border-r-0"
                                                        >
                                                            {header ?? `Col ${i + 1}`}
                                                        </th>
                                                    ))}
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {preview.rows.slice(0, 100).map((row, rowIndex) => (
                                                    <tr
                                                        key={rowIndex}
                                                        className="border-t hover:bg-muted/20"
                                                    >
                                                        {row.map((cell, cellIndex) => (
                                                            <td
                                                                key={cellIndex}
                                                                className="px-3 py-1.5 whitespace-nowrap border-r last:border-r-0 max-w-[200px] overflow-hidden text-ellipsis"
                                                            >
                                                                {cell ?? ""}
                                                            </td>
                                                        ))}
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                    {preview.rows.length > 100 && (
                                        <p className="text-xs text-muted-foreground text-center">
                                            Showing first 100 of {preview.rows.length} rows
                                        </p>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                <DialogFooter className="shrink-0 border-t px-6 py-4">
                    <Button variant="outline" onClick={close}>
                        Close
                    </Button>

                    {preview ? (
                        <Button variant="secondary" disabled>
                            Import Data (Coming Soon)
                        </Button>
                    ) : (
                        <Button
                            onClick={submit}
                            disabled={!file || !branchId}
                        >
                            <Upload className="size-4" />
                            Preview Data
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
