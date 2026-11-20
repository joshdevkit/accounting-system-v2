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
import { FileSpreadsheet, LoaderCircle, Upload, X } from "lucide-react";
import Pos from "@/routes/Pos";

type Branch = { id: number; name: string; code: string };

type PosImportDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    branches: Branch[];
};

const ACCEPTED_EXTENSIONS = [".xlsx", ".xls"];

type Phase = "idle" | "uploading" | "processing";

export default function PosImportDialog({
    open,
    onOpenChange,
    branches,
}: PosImportDialogProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);

    const [branchId, setBranchId] = useState<number | "">("");
    const [file, setFile] = useState<File | null>(null);
    const [dragActive, setDragActive] = useState(false);
    const [phase, setPhase] = useState<Phase>("idle");
    const [uploadPct, setUploadPct] = useState(0);
    const [error, setError] = useState<string | null>(null);

    const selectedBranch = branches.find((b) => b.id === branchId) ?? null;
    const isBusy = phase !== "idle";

    const reset = () => {
        setBranchId("");
        setFile(null);
        setPhase("idle");
        setUploadPct(0);
        setError(null);
    };

    const close = () => {
        if (isBusy) return;
        reset();
        onOpenChange(false);
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
        setPhase("uploading");
        setUploadPct(0);

        router.post(
            Pos.import(),
            { branch_id: branchId, file },
            {
                forceFormData: true,
                onProgress: (event) => {
                    if (event?.percentage == null) return;

                    setUploadPct(event.percentage);

                    // Upload finished sending bytes — server is now parsing.
                    // We have no real signal for this stage, so switch to
                    // an indeterminate indicator instead of sitting at 100%.
                    if (event.percentage >= 100) {
                        setPhase("processing");
                    }
                },
                onSuccess: () => {
                    reset();
                    onOpenChange(false);
                },
                onError: (errors) => {
                    setPhase("idle");
                    setUploadPct(0);
                    setError(
                        errors.file ??
                            errors.branch_id ??
                            "Import failed. Check the file and try again.",
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
                    <DialogTitle>Import POS Data</DialogTitle>
                    <DialogDescription>
                        Upload an Excel file containing your POS data. The
                        importer will detect the spreadsheet data regardless of
                        its visual formatting.
                    </DialogDescription>
                </DialogHeader>

                <div className="min-h-0 flex-1 overflow-auto">
                    <div className="mx-auto flex min-h-full w-full max-w-7xl flex-col p-6">
                        <div className="grid flex-1 place-items-center">
                            <div className="w-full max-w-xl space-y-4">
                                <Field
                                    label="Branch"
                                    htmlFor="pos_import_branch"
                                >
                                    <Combobox
                                        items={branches}
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
                                            id="pos_import_branch"
                                            placeholder="Choose a branch…"
                                            disabled={isBusy}
                                            className="w-full"
                                        />
                                        <ComboboxContent>
                                            <ComboboxList>
                                                {branches.map((branch) => (
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
                                        if (!isBusy) setDragActive(true);
                                    }}
                                    onDragLeave={() => setDragActive(false)}
                                    onDrop={isBusy ? undefined : onDrop}
                                    className={[
                                        "rounded-xl border border-dashed p-10 text-center transition-colors",
                                        dragActive
                                            ? "border-primary bg-primary/5"
                                            : "",
                                    ].join(" ")}
                                >
                                    <div className="bg-muted mx-auto flex size-14 items-center justify-center rounded-xl">
                                        <FileSpreadsheet className="text-muted-foreground size-7" />
                                    </div>

                                    <h3 className="mt-4 text-base font-semibold">
                                        {file
                                            ? file.name
                                            : "Import POS Excel Data"}
                                    </h3>

                                    <p className="text-muted-foreground mx-auto mt-1 max-w-md text-sm">
                                        {file
                                            ? `${(file.size / 1024).toFixed(0)} KB`
                                            : "Upload the Sales Format workbook. Each reference becomes a draft with guest details, income and split payments. The selected branch must match the worksheet name; a single sales sheet is also supported."}
                                    </p>

                                    <div className="mt-6 flex items-center justify-center gap-2">
                                        <input
                                            ref={fileInputRef}
                                            type="file"
                                            accept={ACCEPTED_EXTENSIONS.join(
                                                ",",
                                            )}
                                            className="hidden"
                                            disabled={isBusy}
                                            onChange={(event) => {
                                                const picked =
                                                    event.target.files?.[0];
                                                if (picked)
                                                    validateAndSetFile(picked);
                                                event.target.value = "";
                                            }}
                                        />

                                        <Button
                                            type="button"
                                            variant={
                                                file ? "outline" : "default"
                                            }
                                            disabled={isBusy}
                                            onClick={() =>
                                                fileInputRef.current?.click()
                                            }
                                        >
                                            <Upload className="size-4" />
                                            {file
                                                ? "Choose a different file"
                                                : "Choose Excel File"}
                                        </Button>

                                        {file && !isBusy && (
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

                                {phase !== "idle" && (
                                    <div className="space-y-1.5">
                                        <div className="bg-muted relative h-1.5 w-full overflow-hidden rounded-full">
                                            {phase === "uploading" ? (
                                                <div
                                                    className="bg-primary h-full transition-all duration-150"
                                                    style={{
                                                        width: `${uploadPct}%`,
                                                    }}
                                                />
                                            ) : (
                                                <div className="bg-primary absolute inset-y-0 w-1/3 animate-pos-import-scan rounded-full" />
                                            )}
                                        </div>
                                        <p className="text-muted-foreground text-xs">
                                            {phase === "uploading"
                                                ? `Uploading… ${uploadPct}%`
                                                : "Reading your spreadsheet…"}
                                        </p>
                                    </div>
                                )}

                                {error && (
                                    <p className="text-destructive text-sm">
                                        {error}
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter className="shrink-0 border-t px-6 py-4">
                    <Button variant="outline" onClick={close} disabled={isBusy}>
                        Cancel
                    </Button>

                    <Button
                        onClick={submit}
                        disabled={!file || !branchId || isBusy}
                    >
                        {isBusy ? (
                            <LoaderCircle className="size-4 animate-spin" />
                        ) : (
                            <Upload className="size-4" />
                        )}
                        Import Data
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
